import fs from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const source=path.join(root,'source/calico-mcp');
const map={};const manifest=[];
async function walk(dir) {
 for(const entry of await fs.readdir(path.join(source,dir),{withFileTypes:true})) {
  const rel=dir?dir+'/'+entry.name:entry.name;
  if(entry.isDirectory()) await walk(rel);
  else if(entry.name.endsWith('.php') && !['classmap.php'].includes(entry.name)) {
   const data=await fs.readFile(path.join(source,rel));const code=data.toString();
   const ns=code.match(/^namespace\s+([^;]+);/m)?.[1];
   const name=code.match(/^(?:abstract\s+|final\s+)?class\s+(\w+)/m)?.[1];
   if(ns&&name) map[ns+'\\'+name]=rel;
   manifest.push({file:rel,sha256:createHash('sha256').update(data).digest('hex')});
  }
 }
}
await walk('');
const quote=s=>"'"+s.replaceAll('\\','\\\\').replaceAll("'","\\'")+"'";
await fs.writeFile(path.join(source,'classmap.php'),'<?php\nreturn array(\n'+Object.entries(map).map(([k,v])=>'    '+quote(k)+' => '+quote(v)+',').join('\n')+'\n);\n');
await fs.writeFile(path.join(root,'SOURCE-MANIFEST.json'),JSON.stringify(manifest,null,2));
console.log('Mapped '+Object.keys(map).length+' classes.');
