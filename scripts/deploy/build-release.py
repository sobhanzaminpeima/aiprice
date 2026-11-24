"""Build a Windows-friendly production release from committed backend sources."""
import os
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile
import zipfile

root = Path(__file__).resolve().parents[2]

def run(args, cwd=root):
    subprocess.run(args, cwd=cwd, check=True)

if subprocess.check_output(['git', 'status', '--porcelain', '--', 'backend', 'frontend'], cwd=root).strip():
    raise SystemExit('Commit backend and frontend changes before building a release.')
sha = subprocess.check_output(['git', 'rev-parse', '--short', 'HEAD'], cwd=root).decode().strip()
build = root / 'build'
build.mkdir(exist_ok=True)
stage = Path(tempfile.mkdtemp(prefix=f'release-{sha}-', dir=build))
run(['npm.cmd' if os.name == 'nt' else 'npm', 'run', 'build'], root / 'frontend')
archive = build / f'backend-{sha}.zip'
with archive.open('wb') as output:
    subprocess.run(['git', 'archive', '--format=zip', 'HEAD:backend'], cwd=root, stdout=output, check=True)
with zipfile.ZipFile(archive) as source:
    source.extractall(stage)
composer = [os.environ.get('PHP_BIN', 'php'), os.environ.get('COMPOSER_PHAR', r'C:\composer\composer.phar')]
original = (stage / 'composer.json').read_bytes()
run(composer + ['config', 'platform.php', os.environ.get('SERVER_PHP', '8.3.37')], stage)
run(composer + ['install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-scripts', '--quiet'], stage)
run(composer + ['check-platform-reqs', '--no-dev'], stage)
(stage / 'composer.json').write_bytes(original)
shutil.copytree(root / 'frontend' / 'dist', stage / 'public', dirs_exist_ok=True)
(stage / 'public' / 'index.html').rename(stage / 'public' / 'spa.html')
(stage / 'RELEASE').write_text(sha + '\n', encoding='ascii')
target = build / f'aiprice-release-{sha}.tar.gz'
with tarfile.open(target, 'w:gz') as package:
    for child in stage.iterdir():
        if child.name not in ('tests', 'phpunit.xml'):
            package.add(child, arcname='./' + child.name)
print('Release:', target)
