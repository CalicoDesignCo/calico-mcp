// Add only explicitly retained integrations; never rerun the original importer over hardened files.
import fs from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const upstream=path.resolve(root,'../MCP Review 1.5.2/miniorange-secure-mcp-server');
const additions=[];
for(const dir of ['elementor','forms']) {
 const rel='libraries/abilities/src/packs/'+dir;
 for(const name of await fs.readdir(path.join(upstream,rel))) {
  if(!name.endsWith('.php')||name==='index.php')continue;
  const file=rel+'/'+name,data=await fs.readFile(path.join(upstream,file),'utf8');
  const output=data.replaceAll('miniorange-secure-mcp-server','calico-mcp').replaceAll('MoSMCP','CalicoMCP').replaceAll('MOSMCP','CALICOMCP').replaceAll('mosmcp','calicomcp');
  const target=path.join(root,'source/calico-mcp',file);await fs.mkdir(path.dirname(target),{recursive:true});
  try {await fs.access(target);throw Error('Refuse overwrite: '+file);}catch(e){if(e.code!=='ENOENT')throw e;}
  await fs.writeFile(target,output);additions.push({file,upstream_sha256:createHash('sha256').update(data).digest('hex')});
 }
}
await fs.writeFile(path.join(root,'UPSTREAM-ADDITIONS.json'),JSON.stringify({source:'miniOrange 1.5.2',license:'MIT/Expat; original notices retained',files:additions},null,2));
console.log('Added '+additions.length+' integration source files.');
