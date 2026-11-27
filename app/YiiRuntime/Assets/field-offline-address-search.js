((root) => {
  "use strict";
  const normalize = (value) => String(value ?? "")
    .toLocaleLowerCase("ru").replaceAll("ё", "е").replaceAll("\u00a0", " ")
    .replace(/пр-(?:т|кт)/gu,"проспект").replaceAll("б-р","бульвар").replaceAll("пр-д","проезд")
    .replace(/(?<=[а-я])-+(?=[а-я])/gu, " ")
    .replace(/(?<=[а-я])\.(?=\d)|(?<=\d)\.(?=[а-я])/gu, "")
    .replace(/(?<=\d)(?=[ксп]\s*№?\s*\d)/gu, " ").replace(/[,.]+/gu, " ").replace(/\s+/gu, " ").trim();
  const component = normalize;
  const entrance = (value) => {
    value=normalize(value);const match=value.match(/^(?:(?:подъезд|подезд|под|п)\s*)?№?\s*(\d+)$/u);return match?match[1]:value;
  };
  const streetTypes=new Set(["улица","проспект","переулок","шоссе","набережная","бульвар","проезд"]);
  const streetWord = (word) => {
    word = word.replace(/^-+|-+$/g, "");
    const aliases = {ул:"улица",улица:"улица",просп:"проспект",проспект:"проспект","пр-т":"проспект","пр-кт":"проспект",пер:"переулок",переулок:"переулок",ш:"шоссе",шоссе:"шоссе",наб:"набережная",набережная:"набережная","б-р":"бульвар",бул:"бульвар",бульвар:"бульвар","пр-д":"проезд",проезд:"проезд"};
    return Object.hasOwn(aliases, word) ? aliases[word] : word;
  };
  function parse(value, query) {
    let text = normalize(value)
      .replace(/(^|\s)(дом|д)\s*№?\s*(\d+[а-я]?(?:\/\d+)?)(?=\s|$)/gu, "$1 house:$3 ")
      .replace(/(^|\s)(корпус|корп|к)\s*№?\s*(\d+[а-я]?)(?=\s|$)/gu, "$1 corpus:$3 ")
      .replace(/(^|\s)(строение|стр|с)\s*№?\s*(\d+[а-я]?)(?=\s|$)/gu, "$1 building:$3 ")
      .replace(/(^|\s)(подъезд|подезд|под|п)\s*№?\s*(\d+)(?=\s|$)/gu, "$1 entrance:$3 ");
    const parts=text.trim().split(/\s+/u).filter(Boolean), values={house:[],corpus:[],building:[],entrance:[]}, numbers=[], words=[], types=[];
    let lastNameIndex=null;
    parts.forEach((part,index)=>{if(!/^(?:house|corpus|building|entrance):/u.test(part)&&!/^\d+[а-я]?(?:\/\d+)?$/u.test(part))lastNameIndex=index;});
    parts.forEach((part,index) => {
      const marked=part.match(/^(house|corpus|building|entrance):(.*)$/u);
      if(marked) values[marked[1]].push(component(marked[2]));
      else if(/^\d+[а-я]?(?:\/\d+)?$/u.test(part) && (lastNameIndex===null||index>lastNameIndex)) numbers.push(part);
      else { const word=streetWord(part); if(streetTypes.has(word))types.push(word);else if(word)words.push(word); }
    });
    if(numbers.length){const slots=["house","corpus","entrance"];for(const number of numbers){while(slots.length&&values[slots[0]].length)slots.shift();if(!slots.length){values.house.push("__conflict__");break;}values[slots.shift()].push(component(number));}}
    const uniqueTypes=[...new Set(types)],result={street:words.join(" "),words,streetType:uniqueTypes[0]||"",conflict:uniqueTypes.length>1};
    Object.entries(values).forEach(([name,items])=>{items=[...new Set(items.filter(Boolean))];if(items.length>1)result.conflict=true;result[name]=items[0]||"";});
    return result;
  }
  const distance=(left,right)=>{const a=Array.from(left),b=Array.from(right);let prev=Array.from({length:b.length+1},(_,i)=>i);a.forEach((ca,i)=>{const next=[i+1];b.forEach((cb,j)=>next.push(Math.min(next[j]+1,prev[j+1]+1,prev[j]+(ca===cb?0:1))));prev=next;});return prev[b.length];};
  function ids(records, query) {
    records=Array.from(records);const normalizedQuery=normalize(query);if(!normalizedQuery)return records.map(x=>x.id);
    const wanted=parse(query,true);if(wanted.conflict)return [];
    let candidates=new Map();
    records.forEach((record,index)=>{
      if((record.extra||[]).some(value=>{value=normalize(value);return value&&value.includes(normalizedQuery);})){candidates.set(index,{street:"",extraMatch:true});return;}
      const found=parse(record.address||"",false), entranceValue=entrance(record.entrance||"");if(entranceValue)found.entrance=entranceValue;
      if(wanted.streetType&&found.streetType&&wanted.streetType!==found.streetType)return;
      if(["house","corpus","building","entrance"].some(name=>wanted[name]&&wanted[name]!==found[name]))return;
      candidates.set(index,{street:found.street,extraMatch:false});
    });
    for(const word of wanted.words){const exact=new Map(),docs=[],owners=[];for(const [index,candidate] of candidates){const exactWord=/\d/u.test(word)?candidate.street.split(" ").includes(word):candidate.street.includes(word);if(candidate.extraMatch||exactWord)exact.set(index,candidate);else if(Array.from(word).length>=5)for(const candidateWord of candidate.street.split(" "))if(Array.from(candidateWord).length>=5){docs.push(candidateWord);owners.push(index);}}
      if(docs.length){const fuse=new root.Fuse(docs,{threshold:Array.from(word).length<=5?.2:.28,distance:1000,ignoreLocation:true,minMatchCharLength:Math.max(2,Array.from(word).length-1),shouldSort:false});for(const match of fuse.search(word)){const position=match.refIndex;if(distance(word,docs[position])>Math.max(1,Math.floor(Array.from(word).length*.28)))continue;const owner=owners[position];exact.set(owner,candidates.get(owner));}}
      candidates=exact;if(!candidates.size)break;
    }
    return records.flatMap((record,index)=>candidates.has(index)?[record.id]:[]);
  }
  root.FMonitorAddressSearch=Object.freeze({ids});
})(globalThis);
