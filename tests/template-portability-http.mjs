import assert from 'node:assert/strict';
import {readFileSync, writeFileSync} from 'node:fs';
const path=process.argv[2];
const state=JSON.parse(readFileSync(path,'utf8'));
const base=new URL(state.base);
assert.ok(/(?:\.local|\.test)$|^localhost$/.test(base.hostname));
const page='/wp-admin/edit.php?post_type=ediworman_template&page=ediworman-template-portability';
const request=(url,options={})=>{
 const target=new URL(url,base);assert.equal(target.origin,base.origin);
 return fetch(target,{...options,headers:{...options.headers,Cookie:state.cookie},redirect:'manual'});
};
const save=()=>writeFileSync(path,JSON.stringify(state));
const nonceFor=(html,action)=>{
  const form=[...html.matchAll(/<form\b[\s\S]*?<\/form>/g)].map(m=>m[0]).find(s=>s.includes(`value="ediworman_template_${action}"`));
  assert.ok(form,`Missing ${action} form`);
  return form.match(/name="_wpnonce"[^>]*value="([^"]+)"/)[1];
};
const getPage=async()=>{
  const response=await request(page); assert.equal(response.status,200); return response.text();
};
const html=await getPage();
assert.ok(html.includes('JSON template file')&&html.includes('Validate and preview'));
const exported=await request('/wp-admin/admin-post.php',{method:'POST',body:new URLSearchParams({action:'ediworman_template_export',template_id:String(state.ids[0]),_wpnonce:nonceFor(html,'export')})});
assert.equal(exported.status,200);
assert.match(exported.headers.get('content-type'),/application\/json/);
assert.match(exported.headers.get('content-disposition'),/attachment/);
assert.match(exported.headers.get('cache-control'),/no-cache/);
const json=await exported.text();
const document=JSON.parse(json); assert.equal(document.schema_version,1);
assert.equal(Object.keys(document.template.automatic_requirements).length,12);
assert.ok(!('post_type_templates' in document.template));
const upload=async(bytes,name='transfer.json',nonce=nonceFor(html,'preview'))=>{
 const form=new FormData();form.set('action','ediworman_template_preview');form.set('_wpnonce',nonce);form.set('ediworman_template_file',new Blob([bytes],{type:'application/json'}),name);
 return request('/wp-admin/admin-post.php',{method:'POST',body:form});
};
assert.equal((await upload(json,'transfer.json','invalid')).status,403);
assert.equal((await upload('{')).status,400);
assert.equal((await upload(json,'transfer.php')).status,400);
assert.equal((await upload(' '.repeat(1048577))).status,400);
const stage=async()=>{
 const response=await upload(json);assert.equal(response.status,302);
 const location=response.headers.get('location');const url=new URL(location,base);const token=url.searchParams.get('ediworman_preview');assert.ok(token);
 state.pending_tokens.push(token);save();
 const preview=await request(location);assert.equal(preview.status,200);const body=await preview.text();
 assert.ok(body.includes('provider')&&body.includes('unavailable')&&body.includes('Create new template'));
 assert.ok(body.includes('Optional final review')&&body.includes('minimum 2'));
 return {token,body,location};
};
const first=await stage();
const invalid=await request('/wp-admin/admin-post.php',{method:'POST',body:new URLSearchParams({action:'ediworman_template_confirm',_wpnonce:'invalid',ediworman_token:first.token})});assert.equal(invalid.status,403);
const cancel=await request('/wp-admin/admin-post.php',{method:'POST',body:new URLSearchParams({action:'ediworman_template_cancel',_wpnonce:nonceFor(first.body,'cancel'),ediworman_token:first.token})});assert.equal(cancel.status,302);
const cancelled=await request(first.location);assert.ok((await cancelled.text()).includes('unavailable or expired'));
const second=await stage();
const confirmBody=new URLSearchParams({action:'ediworman_template_confirm',_wpnonce:nonceFor(second.body,'confirm'),ediworman_token:second.token});
const results=await Promise.all([request('/wp-admin/admin-post.php',{method:'POST',body:confirmBody}),request('/wp-admin/admin-post.php',{method:'POST',body:confirmBody})]);
// Record every returned copy before asserting: cleanup must also work if a regression creates two.
for(const response of results.filter(r=>r.status===302)) {
 const location=response.headers.get('location');
 const id=location?Number(new URL(location,base).searchParams.get('ediworman_imported')):0;
 if(id&&!state.ids.includes(id))state.ids.push(id);
}
save();
assert.equal(results.filter(r=>r.status===302).length,1,'Concurrent confirmation created more than one success');
assert.equal(results.filter(r=>r.status===400).length,1);
const location=results.find(r=>r.status===302).headers.get('location');
const id=Number(new URL(location,base).searchParams.get('ediworman_imported'));assert.ok(id);
const success=await request(location);assert.ok((await success.text()).includes('Edit imported template'));
const replay=await request('/wp-admin/admin-post.php',{method:'POST',body:confirmBody});assert.equal(replay.status,400);
// Leave one live preview for a native-browser visual/accessibility spot check.
const visual=await stage();
state.visualPreview=visual.location;save();
console.log('HTTP checks passed: authenticated export headers/round trip, invalid nonce, malformed/type/oversized uploads, preview, cancellation, concurrent confirmation, success, and replay.');
