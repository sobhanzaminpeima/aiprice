"""Configure ignored local server environment and probe public model availability.

The supplied key is read only from RUNCRATE_SETUP_KEY, never printed or written
to frontend files. Existing environment values and other providers are preserved.
"""
import os,pathlib,re,json,urllib.request,urllib.error
root=pathlib.Path(__file__).resolve().parent.parent
key=os.environ['RUNCRATE_SETUP_KEY'];path=root/'backend/.env'
text=path.read_text(encoding='utf-8')
values={'AI_PROVIDER':'runcrate','RUNCRATE_API_KEY':key,'RUNCRATE_BASE_URL':'https://api.runcrate.ai/v1','RUNCRATE_MODEL':'Qwen/Qwen3.8-Flash'}
for name,value in values.items():
    line=name+'="'+value.replace('"','\\"')+'"'
    if re.search(r'^'+name+'=',text,re.M):text=re.sub(r'^'+name+r'=.*$',lambda _:line,text,flags=re.M)
    else:text+='\n'+line
path.write_text(text.rstrip()+'\n',encoding='utf-8')
request=urllib.request.Request('https://api.runcrate.ai/v1/models',headers={'Authorization':'Bearer '+key,'Accept':'application/json'})
try:
    with urllib.request.urlopen(request,timeout=30) as response:data=json.load(response)
    ids=[r['id'] for r in data.get('data',[])];wanted=['Qwen/Qwen3.8-Flash','deepseek-ai/DeepSeek-V4.1-Flash-1017','deepseek-ai/DeepSeek-V4.1-Flash','zai-org/GLM-5.3','openai/gpt-oss-120b','zai-org/GLM-5.3-Flash']
    result={'configured_local_server':True,'models_http':200,'available_requested_models':[m for m in wanted if m in ids],'requested_models_not_listed':[m for m in wanted if m not in ids],'total_models':len(ids)}
except urllib.error.HTTPError as error:result={'configured_local_server':True,'models_http':error.code,'models_verified':False}
(root/'output/runcrate-connectivity.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print(json.dumps(result))
