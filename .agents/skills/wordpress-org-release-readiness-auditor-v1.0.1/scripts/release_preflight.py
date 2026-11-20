#!/usr/bin/env python3
"""Read-only metadata preflight for WordPress.org release ZIPs or directories.

Python 3.9+ standard library; no extraction, PHP execution, writes or network.
Does not audit upgrade behavior or replace Plugin Check.
"""
import argparse
import json
import re
import stat
import sys
import zipfile
from pathlib import Path

KEYS = r'Plugin Name|Version|Stable tag|Text Domain|Requires at least|Requires PHP|Tested up to|License'
FIELD = re.compile(r'^\s*(?:\*\s*)?(' + KEYS + r')\s*:\s*(.*?)\s*$', re.I)

def headers(text):
    result = {}
    for line in text.splitlines()[:160]:
        match = FIELD.match(line)
        if match:
            result[match.group(1).lower()] = match.group(2).strip().rstrip('*/ ').strip()
    return result

def safe_path(name):
    return not (name.startswith('/') or '\\' in name or '..' in name.split('/') or re.match(r'^[A-Za-z]:', name))

def audit(path, slug, version):
    findings = []
    def add(code, level, location, actual, expected, fix):
        findings.append(dict(code=code, severity=level, path=location, observed=actual, expected=expected, action=fix))
    if path.is_file() and zipfile.is_zipfile(path):
        kind, root = 'ZIP', slug + '/'
        with zipfile.ZipFile(path) as z:
            names = [i.filename for i in z.infolist() if not i.is_dir()]
            for item in z.infolist():
                if not item.is_dir() and stat.S_IFMT(item.external_attr >> 16) == stat.S_IFLNK:
                    add('ZIP_SYMLINK','WARNING',item.filename,'Symlink member','Regular release files','Review link handling.')
            for name in names:
                if not safe_path(name):
                    add('UNSAFE_ARCHIVE_PATH','BLOCKER',name,'Unsafe entry path','Safe relative path','Rebuild archive.')
            if not names or any(not name.startswith(root) for name in names):
                add('ZIP_ROOT_LAYOUT','BLOCKER',path.name,'Incorrect package root',root,'Build a single slug-root ZIP.')
            data = {}
            for name in names:
                rest = name[len(root):] if name.startswith(root) else name
                if safe_path(name) and (name == root+'readme.txt' or ('/' not in rest and rest.endswith('.php'))):
                    info = z.getinfo(name)
                    if info.file_size <= 131072:
                        try: data[name] = z.read(info).decode('utf-8-sig', errors='replace')
                        except (OSError, RuntimeError, zipfile.BadZipFile):
                            add('UNREADABLE_ENTRY','BLOCKER',name,'Could not read entry','Readable file','Rebuild ZIP.')
                    else:
                        add('LARGE_METADATA','WARNING',name,str(info.file_size),'Readable metadata','Inspect manually.')
    elif path.is_dir():
        kind, root = 'DIRECTORY', ''
        files = [p for p in path.rglob('*') if p.is_file() and not p.is_symlink()]
        names = [p.relative_to(path).as_posix() for p in files]
        data = {}
        for name in names:
            if name == 'readme.txt' or ('/' not in name and name.endswith('.php')):
                f = path / name
                if f.stat().st_size <= 131072:
                    data[name] = f.read_text(encoding='utf-8-sig', errors='replace')
    else:
        raise ValueError('Candidate must be a directory or a valid ZIP')

    mains = [(name, headers(body[:8192])) for name, body in data.items()
             if name.endswith('.php') and headers(body[:8192]).get('plugin name')]
    main = None
    if mains:
        main = next((x for x in mains if x[0] == root+slug+'.php'), mains[0])
        if len(mains) > 1:
            add('MULTIPLE_MAIN_HEADERS','WARNING',root,','.join(x[0] for x in mains),'One clear main plugin','Confirm entrypoint.')
        if main[1].get('version') != version:
            add('VERSION_TARGET_MISMATCH','BLOCKER',main[0],main[1].get('version','(missing)'),version,'Align release version.')
        if main[1].get('text domain') and main[1]['text domain'] != slug:
            add('TEXT_DOMAIN_MISMATCH','WARNING',main[0],main[1]['text domain'],slug,'Align WordPress.org text domain.')
        if not main[1].get('license'):
            add('LICENSE_REVIEW','WARNING',main[0],'No license header','License evidence','Review licenses manually.')
    else:
        add('MAIN_PLUGIN_MISSING','BLOCKER',root or str(path),'No root PHP Plugin Name header','Main plugin entrypoint','Include main PHP file.')

    readme_name = root + 'readme.txt'
    if readme_name not in data:
        add('README_MISSING','BLOCKER',readme_name,'Missing/unreadable','readme.txt at plugin root','Include readme.txt.')
    else:
        body = data[readme_name]
        readme = headers(body)
        if readme.get('stable tag') != version:
            add('STABLE_TAG_MISMATCH','BLOCKER',readme_name,readme.get('stable tag','(missing)'),version,'Align readme stable tag with release.')
        for key, code in [('requires at least','MIN_WP_MISMATCH'),('requires php','MIN_PHP_MISMATCH')]:
            if main and readme.get(key) and main[1].get(key) and readme[key] != main[1][key]:
                add(code,'WARNING',main[0]+' + '+readme_name,main[1][key]+' vs '+readme[key],'Consistent minimum requirements','Verify actual support and align metadata.')
        changelog = re.search(r'^\s*==\s*Changelog\s*==\s*$',body,re.I|re.M)
        target = re.search(r'^\s*=\s*'+re.escape(version)+r'(?:\s+[^=]*)?\s*=\s*$',body[changelog.end():] if changelog else '',re.I|re.M)
        if not changelog or not target:
            add('CHANGELOG_VERSION_MISSING','WARNING',readme_name,'No current version entry',version,'Add accurate changelog entry.')
        if not readme.get('tested up to'):
            add('TESTED_UP_TO_REVIEW','WARNING',readme_name,'(missing)','Evidence-backed tested version','Declare only verified test range.')

    relative = [name[len(root):] if name.startswith(root) else name for name in names]
    for directory in ('.git/', '.github/', 'node_modules/', 'tests/', '__pycache__/'):
        if any(name.startswith(directory) for name in relative):
            add('DEV_ARTIFACT','WARNING',directory,'Development files present','Purpose-built release package','Review whether these files ship.')
    for name in relative:
        if any(x in {'.env','.env.local','id_rsa','credentials.json'} for x in name.lower().split('/')):
            add('POTENTIAL_PRIVATE_FILE','BLOCKER' if kind == 'ZIP' else 'WARNING',root+name,'Sensitive-looking filename','No private files','Remove any secrets; do not publish.')
    count = {x+'s':sum(f['severity']==x.upper() for f in findings) for x in ('blocker','warning')}
    return {'tool':'WP.org Release Readiness Auditor read-only scanner v1.0.1','input':str(path),
            'kind':kind,'slug':slug,'version':version,'static_gate':'HOLD' if count['blockers'] else 'VERIFY BEFORE RELEASE',
            'counts':count,'findings':findings,
            'not_checked':['Persisted configuration upgrade preservation','WordPress.org SVN tag/trunk',
                           'Official Plugin Check and readme validator','WordPress installation, runtime and upgrades',
                           'Licensing adjudication and human publication']}

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--path',type=Path,required=True)
    parser.add_argument('--slug',required=True)
    parser.add_argument('--version',required=True)
    parser.add_argument('--format',choices=('json','markdown'),default='markdown')
    args = parser.parse_args()
    if not re.fullmatch(r'[a-z0-9][a-z0-9-]*',args.slug) or not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._+-]*',args.version):
        parser.error('Provide a lowercase slug and simple version identifier')
    try: report = audit(args.path,args.slug,args.version)
    except (OSError,ValueError,zipfile.BadZipFile) as exc:
        print('Could not inspect candidate: '+str(exc),file=sys.stderr)
        return 3
    if args.format == 'json':
        print(json.dumps(report,ensure_ascii=False,indent=2))
    else:
        print('# Local release metadata preflight\n\n**Static gate:** '+report['static_gate'])
        for f in report['findings']:
            print('- {} [{}] — {}: {} (expected {}). {}'.format(f['code'],f['severity'],f['path'],f['observed'],f['expected'],f['action']))
        print('\n**Not checked:** '+', '.join(report['not_checked']))
        print('\n*Not official Plugin Check or release approval.*')
    return 2 if report['counts']['blockers'] else 0

if __name__=='__main__':
    sys.exit(main())
