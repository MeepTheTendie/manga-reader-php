<!DOCTYPE html>
<html lang="en">
<head>
    <base href="/">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manga Library</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a1a;
            color: #e0e0e0;
            min-height: 100vh;
        }
        
        header {
            background: #0d0d0d;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #333;
        }
        
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #fff;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .loading {
            text-align: center;
            padding: 4rem;
            color: #888;
        }
        
        .error {
            background: #3d1f1f;
            color: #ff8080;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        
        .series-grid {
            display: grid;
            gap: 2rem;
        }
        
        .series {
            background: #252525;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #333;
        }
        
        .series-header {
            background: #1f1f1f;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #333;
        }
        
        .series-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }
        
        .series-header .volume-count {
            font-size: 0.85rem;
            color: #888;
            margin-top: 0.25rem;
        }
        
        .volumes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
        }
        
        .volume-card {
            display: block;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s;
        }
        
        .volume-card:hover {
            transform: translateY(-4px);
        }
        
        .volume-cover {
            width: 100%;
            aspect-ratio: 2/3;
            background: #1a1a1a;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        
        .volume-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .volume-cover .placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #2a2a2a;
            color: #666;
            font-size: 0.75rem;
        }
        
        .volume-cover .progress-indicator {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(0,0,0,0.5);
        }
        
        .volume-cover .progress-bar {
            height: 100%;
            background: #4a9eff;
            transition: width 0.3s;
        }
        
        .volume-name {
            padding: 0.75rem 0.25rem 0;
            font-size: 0.85rem;
            color: #ccc;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        
        .empty-state h2 {
            color: #888;
            margin-bottom: 0.5rem;
        }
        
        footer {
            text-align: center;
            padding: 2rem;
            color: #666;
            font-size: 0.85rem;
            border-top: 1px solid #333;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <header>
        <h1>📚 Manga Library</h1>
    </header>
    
    <main class="container">
        <div id="content">
            <div class="loading">Loading library...</div>
        </div>
    </main>
    
    <footer>
        Manga Reader PHP
    </footer>
    
    <script>
        async function loadLibrary() {
            const content = document.getElementById('content');
            
            try {
                const [libraryRes, progressRes] = await Promise.all([
                    fetch('api/library'),
                    fetch('api/progress')
                ]);
                
                const library = await libraryRes.json();
                const progress = await progressRes.json();
                
                if (Object.keys(library).length === 0) {
                    content.innerHTML = `
                        <div class="empty-state">
                            <h2>No manga found</h2>
                            <p>Add manga files to your Documents folder</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '<div class="series-grid">';
                
                for (const [seriesName, seriesData] of Object.entries(library)) {
                    const volumeCount = seriesData.volumes.length;
                    
                    html += `
                        <div class="series">
                            <div class="series-header">
                                <h2>${escapeHtml(seriesName)}</h2>
                                <div class="volume-count">${volumeCount} volume${volumeCount !== 1 ? 's' : ''}</div>
                            </div>
                            <div class="volumes-grid">
                    `;
                    
                    for (const volume of seriesData.volumes) {
                        const coverUrl = `api/manga/${encodeURIComponent(volume.path)}/cover`;
                        const readUrl = `read/${encodeURIComponent(volume.path)}`;
                        const volProgress = progress[volume.path];
                        // Progress percentage (default to 0 if unknown, will be updated when opened)
                        const progressPercent = volProgress && volProgress.total_pages > 0 ? 
                            Math.round((volProgress.page_index / volProgress.total_pages) * 100) : 0;
                        
                        html += `
                            <a href="${readUrl}" class="volume-card" title="${escapeHtml(volume.name)}">
                                <div class="volume-cover">
                                    <img src="${coverUrl}" alt="${escapeHtml(volume.name)}" 
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="placeholder" style="display:none">No Cover</div>
                                    ${volProgress ? `<div class="progress-indicator"><div class="progress-bar" style="width:${progressPercent}%"></div></div>` : ''}
                                </div>
                                <div class="volume-name">${escapeHtml(volume.name)}</div>
                            </a>
                        `;
                    }
                    
                    html += '</div></div>';
                }
                
                html += '</div>';
                content.innerHTML = html;
                
            } catch (err) {
                content.innerHTML = `
                    <div class="error">
                        Failed to load library: ${escapeHtml(err.message)}
                    </div>
                `;
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        loadLibrary();
    </script>
</body>
</html>
