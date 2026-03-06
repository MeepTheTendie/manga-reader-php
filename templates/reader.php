<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reading - Manga Reader</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0d0d0d;
            color: #e0e0e0;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(13, 13, 13, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            border-bottom: 1px solid #333;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .back-btn {
            color: #888;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }
        
        .back-btn:hover {
            color: #fff;
        }
        
        .title {
            font-size: 1rem;
            font-weight: 500;
            color: #fff;
        }
        
        .page-indicator {
            font-size: 0.9rem;
            color: #888;
        }
        
        .reader-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 5rem 1rem 2rem;
        }
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 60vh;
            color: #666;
        }
        
        .error {
            background: #3d1f1f;
            color: #ff8080;
            padding: 1.5rem;
            border-radius: 4px;
            max-width: 500px;
            text-align: center;
        }
        
        .manga-page {
            max-width: 100%;
            max-height: 85vh;
            margin-bottom: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            cursor: pointer;
        }
        
        .manga-page img {
            max-width: 100%;
            max-height: 85vh;
            display: block;
        }
        
        .navigation {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 1rem;
            z-index: 100;
        }
        
        .nav-btn {
            background: rgba(40, 40, 40, 0.9);
            border: 1px solid #444;
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-btn:hover:not(:disabled) {
            background: #3a3a3a;
            border-color: #555;
        }
        
        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .nav-btn.primary {
            background: #4a9eff;
            border-color: #4a9eff;
        }
        
        .nav-btn.primary:hover {
            background: #3a8eef;
        }
        
        .next-volume-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #4a9eff;
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
            display: none;
            align-items: center;
            gap: 0.5rem;
            z-index: 100;
        }
        
        .next-volume-btn.visible {
            display: flex;
        }
        
        .click-zones {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            z-index: 50;
            pointer-events: none;
        }
        
        .click-zone {
            flex: 1;
            pointer-events: auto;
        }
        
        .click-zone.left { cursor: w-resize; }
        .click-zone.right { cursor: e-resize; }
        
        @media (max-width: 768px) {
            header {
                padding: 0.75rem 1rem;
            }
            
            .title {
                font-size: 0.85rem;
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .navigation {
                bottom: 1rem;
            }
            
            .nav-btn {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <a href="./" class="back-btn">← Back</a>
        <span class="title" id="manga-title">Loading...</span>
        <span class="page-indicator" id="page-indicator">- / -</span>
    </header>
    
    <main class="reader-container" id="reader">
        <div class="loading">Loading...</div>
    </main>
    
    <div class="click-zones">
        <div class="click-zone left" onclick="prevPage()"></div>
        <div class="click-zone right" onclick="nextPage()"></div>
    </div>
    
    <div class="navigation">
        <button class="nav-btn" id="prev-btn" onclick="prevPage()">← Previous</button>
        <button class="nav-btn primary" id="next-btn" onclick="nextPage()">Next →</button>
    </div>
    
    <a href="#" class="next-volume-btn" id="next-volume-btn">
        Next Volume →
    </a>
    
    <script>
        const mangaPath = <?php echo json_encode($decodedPath); ?>;
        let pages = [];
        let currentPage = 0;
        let nextVolume = null;
        let saveTimeout = null;
        
        async function loadManga() {
            const reader = document.getElementById('reader');
            const titleEl = document.getElementById('manga-title');
            
            try {
                const [mangaRes, progressRes] = await Promise.all([
                    fetch(`api/manga/${encodeURIComponent(mangaPath)}/pages`),
                    fetch('api/progress')
                ]);
                
                if (!mangaRes.ok) {
                    const err = await mangaRes.json();
                    throw new Error(err.error || 'Failed to load manga');
                }
                
                const data = await mangaRes.json();
                const progress = await progressRes.json();
                
                pages = data.pages;
                nextVolume = data.next_volume;
                
                if (pages.length === 0) {
                    reader.innerHTML = '<div class="error">No pages found in this archive</div>';
                    return;
                }
                
                // Set title
                const parts = mangaPath.split('/');
                titleEl.textContent = parts[parts.length - 1].replace(/\.[^.]+$/, '');
                document.title = `${titleEl.textContent} - Manga Reader`;
                
                // Restore progress
                const saved = progress[mangaPath];
                if (saved && saved.page_index < pages.length) {
                    currentPage = saved.page_index;
                }
                
                // Show next volume button if available
                if (nextVolume) {
                    const nextBtn = document.getElementById('next-volume-btn');
                    nextBtn.href = `read/${encodeURIComponent(nextVolume.path)}`;
                    nextBtn.classList.add('visible');
                }
                
                renderPage();
                
            } catch (err) {
                reader.innerHTML = `<div class="error">${escapeHtml(err.message)}</div>`;
            }
        }
        
        function renderPage() {
            const reader = document.getElementById('reader');
            const pageIndicator = document.getElementById('page-indicator');
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            
            if (pages.length === 0) return;
            
            const pageUrl = `api/manga/${encodeURIComponent(mangaPath)}/page/${encodeURIComponent(pages[currentPage])}`;
            
            reader.innerHTML = `
                <div class="manga-page" onclick="handlePageClick(event)">
                    <img src="${pageUrl}" alt="Page ${currentPage + 1}">
                </div>
            `;
            
            pageIndicator.textContent = `${currentPage + 1} / ${pages.length}`;
            
            prevBtn.disabled = currentPage === 0;
            
            if (currentPage >= pages.length - 1 && nextVolume) {
                nextBtn.textContent = 'Finish →';
                nextBtn.onclick = () => {
                    window.location.href = `read/${encodeURIComponent(nextVolume.path)}`;
                };
            } else {
                nextBtn.textContent = 'Next →';
                nextBtn.onclick = nextPage;
                nextBtn.disabled = currentPage >= pages.length - 1;
            }
            
            // Save progress
            saveProgress();
            
            // Scroll to top
            window.scrollTo(0, 0);
        }
        
        function nextPage() {
            if (currentPage < pages.length - 1) {
                currentPage++;
                renderPage();
            }
        }
        
        function prevPage() {
            if (currentPage > 0) {
                currentPage--;
                renderPage();
            }
        }
        
        function handlePageClick(event) {
            const rect = event.currentTarget.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const width = rect.width;
            
            // Click on right side = next, left side = previous
            if (x > width / 2) {
                nextPage();
            } else {
                prevPage();
            }
        }
        
        function saveProgress() {
            if (saveTimeout) {
                clearTimeout(saveTimeout);
            }
            
            saveTimeout = setTimeout(async () => {
                try {
                    await fetch('api/progress', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            manga_path: mangaPath,
                            page_index: currentPage
                        })
                    });
                } catch (err) {
                    // Silent fail
                }
            }, 500);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === ' ') {
                e.preventDefault();
                nextPage();
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                prevPage();
            }
        });
        
        loadManga();
    </script>
</body>
</html>
