'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..');
const context={console};context.globalThis=context;vm.createContext(context);
// Ready engine and matcher are local assets, executable without network or DOM.
vm.runInContext(fs.readFileSync(path.join(root,'public/third-party/fuse/fuse.min.js'),'utf8'),context);
vm.runInContext(fs.readFileSync(path.join(root,'app/YiiRuntime/Assets/field-offline-address-search.js'),'utf8'),context);
const corpus=JSON.parse(fs.readFileSync(path.join(__dirname,'corpus.json')));
for(const c of corpus.cases)assert.deepEqual(Array.from(context.FMonitorAddressSearch.ids(corpus.records,c.query)),c.ids,c.query);
console.log('PASS: offline address corpus parity without network');
