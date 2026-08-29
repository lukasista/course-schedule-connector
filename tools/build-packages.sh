set -e
cd "$(dirname "$0")/.."
rm -rf /tmp/build && mkdir -p /tmp/build/course-schedule-connector
python3 - <<'PY'
import os,shutil,fnmatch,io,re
ignore=[l.strip() for l in open('.distignore') if l.strip() and not l.startswith('#')]
src='.'; dst='/tmp/build/course-schedule-connector'
def skip(rel):
    parts=rel.split('/')
    for pat in ignore:
        if pat in parts or fnmatch.fnmatch(parts[-1],pat): return True
    return False
for root,dirs,files in os.walk(src):
    rel=os.path.relpath(root,src); rel='' if rel=='.' else rel
    dirs[:]=[d for d in dirs if not skip((rel+'/'+d).lstrip('/'))]
    for f in files:
        r=(rel+'/'+f).lstrip('/')
        if skip(r): continue
        t=os.path.join(dst,r); os.makedirs(os.path.dirname(t),exist_ok=True)
        shutil.copy2(os.path.join(root,f),t)
PY
cd /tmp/build && rm -f /tmp/cscs-dist.zip && zip -qr /tmp/cscs-dist.zip course-schedule-connector
python3 - <<'PY'
import shutil,io,re,os
dst='/tmp/build/course-schedule-connector'
shutil.rmtree(os.path.join(dst,'includes/Updater'), ignore_errors=True)
p=os.path.join(dst,'course-schedule-connector.php')
s=io.open(p,encoding='utf-8').read()
s=re.sub(r'^ \* Update URI:.*\n','',s,flags=re.M)
io.open(p,'w',encoding='utf-8').write(s)
assert 'Update URI' not in s
PY
cd /tmp/build && rm -f /tmp/cscs-wporg.zip && zip -qr /tmp/cscs-wporg.zip course-schedule-connector
ls -lh /tmp/cscs-dist.zip /tmp/cscs-wporg.zip
