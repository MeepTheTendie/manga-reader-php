import os, tempfile, subprocess, time, urllib.request, urllib.error, json, zipfile, pathlib, socket
ROOT=pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory() as temp:
    root=pathlib.Path(temp); library=root/'manga'; library.mkdir(); sibling=root/'manga-private'; sibling.mkdir()
    for directory in [library,sibling]:
        with zipfile.ZipFile(directory/'book.cbz','w') as z:z.writestr('page.jpg',b'fake-test-image')
    env={**os.environ,'MANGA_ROOT':str(library),'CACHE_DIR':str(root/'cache')}
    with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
    origin=f'http://127.0.0.1:{port}'
    server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','index.php'],cwd=ROOT,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    def request(path,body=None,headers=None):
        data=json.dumps(body).encode() if body is not None else None
        try:r=urllib.request.urlopen(urllib.request.Request(origin+path,data=data,headers={'Content-Type':'application/json',**(headers or {})}),timeout=5)
        except urllib.error.HTTPError as error:r=error
        return r.status,r.read().decode()
    try:
        for _ in range(50):
            try:request('/');break
            except OSError:time.sleep(.1)
        assert request('/api/manga/..%2Fmanga-private%2Fbook.cbz/pages')[0]==404
        assert request('/api/progress',{'manga_path':'book.cbz','page_index':0})[0]==200
        assert request('/api/progress',{'manga_path':'book.cbz','page_index':-1})[0]==400
        assert request('/api/progress',{'manga_path':'book.cbz','page_index':2})[0]==400
        assert request('/api/progress',{'manga_path':'book.cbz','page_index':0},{'Origin':'https://evil.test'})[0]==403
        assert request('/api/progress',{'manga_path':'book.cbz','page_index':0},{'Content-Type':'text/plain'})[0]==415
        assert request('/',headers={'Host':'evil.test'})[0]==503
        assert 'page_index' in request('/api/progress')[1]
        assert '<base href="/">' in request('/read/book.cbz')[1]
        progress=root/'cache/reading_progress.json';progress.write_text('{corrupt')
        assert request('/api/progress',{'manga_path':'book.cbz','page_index':0})[0]==503
        assert progress.read_text()=='{corrupt'
        progress.unlink()
        code="require 'lib/ProgressTracker.php'; if (!ProgressTracker::updateProgress($argv[1], 0)) exit(1);"
        processes=[subprocess.Popen(['php','-r',code,str(n)],cwd=ROOT,env=env) for n in range(12)]
        assert all(p.wait()==0 for p in processes)
        assert len(json.loads(progress.read_text()))==12
        print('Manga: 13 path, authentication, save-failure and concurrent-write checks passed.')
    finally:server.terminate();server.wait()
