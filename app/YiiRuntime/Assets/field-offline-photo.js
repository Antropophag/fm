(() => {
  "use strict";

  const MAX_PIXELS = 40000000;
  const MAX_SIDE = 2560;
  const TARGET_BYTES = 2621440;
  const HARD_BYTES = 5242880;
  const TYPES = new Set(["image/jpeg", "image/png", "image/webp"]);

  function dimensions(bytes, type) {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    if (type === "image/png" && bytes.length >= 24 && view.getUint32(0) === 0x89504e47) {
      return [view.getUint32(16), view.getUint32(20)];
    }
    if (type === "image/jpeg" && bytes.length >= 4 && view.getUint16(0) === 0xffd8) {
      let offset = 2;
      while (offset + 9 < bytes.length) {
        if (bytes[offset] !== 0xff) break;
        const marker = bytes[offset + 1];
        if ([0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf].includes(marker)) {
          return [view.getUint16(offset + 7), view.getUint16(offset + 5)];
        }
        if (marker === 0xd8 || marker === 0xd9) { offset += 2; continue; }
        const length = view.getUint16(offset + 2);
        if (length < 2) break;
        offset += 2 + length;
      }
    }
    if (type === "image/webp" && bytes.length >= 30 && String.fromCharCode(...bytes.slice(0, 4)) === "RIFF" && String.fromCharCode(...bytes.slice(8, 12)) === "WEBP") {
      const chunk = String.fromCharCode(...bytes.slice(12, 16));
      if (chunk === "VP8X") return [1 + (bytes[24] | bytes[25] << 8 | bytes[26] << 16), 1 + (bytes[27] | bytes[28] << 8 | bytes[29] << 16)];
      if (chunk === "VP8 " && bytes.length >= 30) return [view.getUint16(26, true) & 0x3fff, view.getUint16(28, true) & 0x3fff];
      if (chunk === "VP8L" && bytes.length >= 25) {
        const bits = view.getUint32(21, true);
        return [(bits & 0x3fff) + 1, ((bits >> 14) & 0x3fff) + 1];
      }
    }
    return null;
  }

  async function digest(blob) {
    if (!crypto?.subtle) throw new Error("Проверка фотографии недоступна в этом браузере");
    const hash = new Uint8Array(await crypto.subtle.digest("SHA-256", await blob.arrayBuffer()));
    return Array.from(hash, (byte) => byte.toString(16).padStart(2, "0")).join("");
  }

  function canvasBlob(canvas, type, quality) {
    return new Promise((resolve, reject) => canvas.toBlob(
      (blob) => blob ? resolve(blob) : reject(new Error("Не удалось подготовить фотографию")), type, quality,
    ));
  }

  async function decode(file) {
    if (globalThis.createImageBitmap) {
      try { return await createImageBitmap(file, { imageOrientation: "from-image" }); }
      catch (error) { throw new Error(`Не удалось открыть фотографию: ${error.message || "неподдерживаемый формат"}`); }
    }
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const image = new Image();
      image.onload = () => { URL.revokeObjectURL(url); resolve(image); };
      image.onerror = () => { URL.revokeObjectURL(url); reject(new Error("Не удалось открыть фотографию")); };
      image.src = url;
    });
  }

  async function preparePhoto(file) {
    if (!(file instanceof Blob) || !TYPES.has(file.type)) throw new Error("Этот формат не поддерживается. Выберите JPEG, PNG или WebP");
    if (!file.size || file.size > 40 * 1024 * 1024) throw new Error("Файл фотографии слишком большой");
    const head = new Uint8Array(await file.slice(0, Math.min(file.size, 1048576)).arrayBuffer());
    const headerDimensions = dimensions(head, file.type);
    if (!headerDimensions) throw new Error("Не удалось проверить размеры фотографии; файл повреждён или не поддерживается");
    if (headerDimensions && (!headerDimensions[0] || !headerDimensions[1] || headerDimensions[0] * headerDimensions[1] > MAX_PIXELS)) {
      throw new Error("Разрешение фотографии слишком большое");
    }
    const source = await decode(file);
    try {
      const width = source.width || source.naturalWidth;
      const height = source.height || source.naturalHeight;
      if (!width || !height || width * height > MAX_PIXELS) throw new Error("Разрешение фотографии слишком большое");
      if (file.size <= TARGET_BYTES && ["image/png", "image/webp"].includes(file.type) && Math.max(width, height) <= MAX_SIDE) {
        return { blob: file, name: file.name || "photo", mime: file.type, size: file.size, sha256: await digest(file) };
      }
      const scale = Math.min(1, MAX_SIDE / Math.max(width, height));
      const outputWidth = Math.max(1, Math.round(width * scale));
      const outputHeight = Math.max(1, Math.round(height * scale));
      const canvas = document.createElement("canvas");
      canvas.width = outputWidth; canvas.height = outputHeight;
      const context = canvas.getContext("2d", { alpha: false });
      if (!context) throw new Error("Не удалось подготовить фотографию");
      context.drawImage(source, 0, 0, outputWidth, outputHeight);
      let quality = 0.88;
      let blob = await canvasBlob(canvas, "image/jpeg", quality);
      while (blob.size > TARGET_BYTES && quality > 0.48) {
        quality -= 0.1;
        blob = await canvasBlob(canvas, "image/jpeg", quality);
      }
      if (blob.size > HARD_BYTES) throw new Error("Не удалось уменьшить фотографию до 5 МБ");
      const base = String(file.name || "photo").replace(/\.[^.]+$/, "");
      return { blob, name: `${base}.jpg`, mime: "image/jpeg", size: blob.size, sha256: await digest(blob) };
    } finally {
      if (typeof source.close === "function") source.close();
    }
  }

  globalThis.FMonitorFieldPhoto = Object.freeze({ preparePhoto, dimensions, MAX_PIXELS, MAX_SIDE, TARGET_BYTES, HARD_BYTES });
})();
