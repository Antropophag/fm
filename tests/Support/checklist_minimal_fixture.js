/* CHECKLIST-MINIMAL-001: deterministic public session port for presentation checks. */
(() => {
  let options, snapshot;
  const png = Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='), c => c.charCodeAt(0));
  const previewUrl = URL.createObjectURL(new Blob([png], {type:'image/png'}));
  window.checklistDiscards = [];
  window.checklistFixtureSet = (status = 'queued', attempted = false, photoSectionId = 1) => {
    const operationStatus = ['online','offline','unsaved'].includes(status) ? 'queued' : status;
    const connection = {queued:'pending',retryable_error:'retryable',unsaved:'storage_recovery',rejected:'conflict'}[status] || status;
    snapshot = {projection:options.projection, storage:{}, connection:{state:connection}, unsavedPhotos:status==='unsaved'?[{id:'unsaved-one',sectionId:1,name:'несохранённое.png',previewUrl,message:'Не удалось сохранить фото'}]:[], operations:status==='online'?[]:[
      {clientOperationId:'item-one',type:'item_completed',itemId:28,sectionId:1,status:operationStatus,attempted,installerTabIds:[18]},
      {clientOperationId:'photo-one',type:'photo_uploaded',sectionId:photoSectionId,status:operationStatus,attempted,originalName:'фото.png',previewUrl},
    ]};
    options.onChange(snapshot);
  };
  window.FMonitorField = {open:async value=>{
    options=value;
    window.checklistFixtureSet(new URLSearchParams(location.search).get('state') || 'queued');
    return {close(){},retry:async()=>{},discard:async id=>{
      const op=snapshot.operations.find(x=>x.clientOperationId===id);
      if(!op||op.status!=='queued'||op.attempted)throw Error('Cannot discard attempted operation');
      window.checklistDiscards.push(id);snapshot.operations=snapshot.operations.filter(x=>x.clientOperationId!==id);options.onChange(snapshot);
    }};
  }};
})();
