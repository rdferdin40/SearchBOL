<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Helpers.php';

use BOLSearch\Database;
use BOLSearch\Helpers;

$config = require __DIR__ . '/../config/config.php';
Database::getInstance($config['db']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>BOLSearch - Search Bills of Lading</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <span class="navbar-brand"><i class="bi bi-file-earmark-pdf"></i> BOLSearch</span>
            <span class="text-white">Bills of Lading Search Portal</span>
        </div>
    </nav>
    
    <div class="container-fluid mt-3">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-light"><h5>Search & Filters</h5></div>
                    <div class="card-body">
                        <input type="text" id="searchQuery" class="form-control mb-3" placeholder="Search BOLs...">
                        <div class="mb-3">
                            <label class="form-label">BOL Number</label>
                            <input type="text" id="bolNumber" class="form-control" placeholder="BOL-12345">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Carrier</label>
                            <input type="text" id="carrier" class="form-control">
                        </div>
                        <button onclick="performSearch()" class="btn btn-primary w-100">Search</button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5>Results <span id="resultCount" class="badge bg-primary">0</span></h5>
                    </div>
                    <div class="card-body" id="resultsContainer">
                        <p class="text-center text-muted">Enter search criteria to find documents</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-light"><h5>Document Viewer</h5></div>
                    <div class="card-body" id="pdfViewer" style="height: 600px;">
                        <p class="text-center text-muted mt-5">Select a document to view</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="alert alert-success mt-3">
            <h5><i class="bi bi-check-circle"></i> Backend Fully Operational!</h5>
            <p><strong>What's working:</strong></p>
            <ul>
                <li>Search API: <code>search.php?q=test</code></li>
                <li>PDF Viewing: <code>view.php?id=1</code></li>
                <li>PDF Download: <code>download.php?id=1</code></li>
                <li>CLI Indexing: <code>php tasks/reindex.php</code></li>
            </ul>
            <p>For the complete interactive UI with AJAX search, refer to <code>IMPLEMENTATION_STATUS.md</code></p>
        </div>
    </div>
    
    <script>
    function performSearch() {
        const query = document.getElementById('searchQuery').value;
        const bolNumber = document.getElementById('bolNumber').value;
        const carrier = document.getElementById('carrier').value;
        
        let url = 'search.php?q=' + encodeURIComponent(query);
        if (bolNumber) url += '&bol_number=' + encodeURIComponent(bolNumber);
        if (carrier) url += '&carrier=' + encodeURIComponent(carrier);
        
        fetch(url)
            .then(r => r.json())
            .then(data => {
                document.getElementById('resultCount').textContent = data.total || 0;
                const container = document.getElementById('resultsContainer');
                
                if (data.results && data.results.length > 0) {
                    let html = '';
                    data.results.forEach(doc => {
                        html += `
                            <div class="border-bottom pb-2 mb-2" onclick="viewPDF(${doc.id})">
                                <strong>${doc.file_name}</strong><br>
                                <small>BOL: ${doc.bol_number || 'N/A'} | Carrier: ${doc.carrier || 'N/A'}</small>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p class="text-center text-muted">No results found</p>';
                }
            })
            .catch(e => {
                console.error('Search failed:', e);
                alert('Search failed. Check console for details.');
            });
    }
    
    function viewPDF(id) {
        document.getElementById('pdfViewer').innerHTML = 
            `<iframe src="view.php?id=${id}" style="width:100%;height:100%;border:none;"></iframe>`;
    }
    </script>
</body>
</html>
