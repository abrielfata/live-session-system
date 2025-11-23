<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test OCR Upload</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f3f4f6; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.5rem; margin-bottom: 1.5rem; }
        h1 { font-size: 1.5rem; font-weight: bold; color: #1f2937; margin-bottom: 0.5rem; }
        h2 { font-size: 1.25rem; font-weight: 600; color: #1f2937; margin-bottom: 1rem; }
        p { color: #6b7280; line-height: 1.5; }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-3 { margin-bottom: 0.75rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .mt-1 { margin-top: 0.25rem; }
        .mt-2 { margin-top: 0.5rem; }
        .mt-6 { margin-top: 1.5rem; }
        
        .alert { border: 1px solid; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; }
        .alert-blue { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
        
        label { display: block; color: #374151; font-weight: 500; margin-bottom: 0.5rem; }
        
        input[type="file"] {
            display: block;
            width: 100%;
            padding: 0.5rem;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            cursor: pointer;
            background: #f9fafb;
        }
        input[type="file"]:hover { background: #f3f4f6; border-color: #9ca3af; }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover:not(:disabled) { background: #1d4ed8; }
        .btn-success { background: #16a34a; color: white; }
        .btn-success:hover:not(:disabled) { background: #15803d; }
        .btn-gray { background: #4b5563; color: white; }
        .btn-gray:hover { background: #374151; }
        .btn-full { width: 100%; }
        
        .preview { margin-bottom: 1rem; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .preview img { max-width: 100%; height: auto; display: block; }
        
        .result-box {
            background: #f9fafb;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: auto;
            max-height: 400px;
        }
        pre { font-size: 0.875rem; white-space: pre-wrap; word-wrap: break-word; }
        
        .loading {
            text-align: center;
            padding: 2rem;
        }
        .spinner {
            border: 3px solid #e5e7eb;
            border-top-color: #2563eb;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .hidden { display: none !important; }
        
        .flex { display: flex; gap: 1rem; }
        .flex-1 { flex: 1; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="card">
            <h1>🧪 Test OCR Upload</h1>
            <p>Upload gambar TikTok Live untuk test ekstraksi GMV</p>
        </div>

        <!-- Test API Key First -->
        <div class="alert alert-blue">
            <h2 style="font-size: 1rem; margin-bottom: 0.5rem;">⚠️ Cek API Key Dulu</h2>
            <p class="text-sm mb-3">Sebelum upload gambar, pastikan API key valid.</p>
            <a href="{{ route('test.ocr.key') }}" target="_blank" class="btn btn-primary">
                🔍 Test API Key
            </a>
        </div>

        <!-- Upload Form -->
        <div class="card">
            <h2>Upload Gambar</h2>
            
            <form id="uploadForm" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label for="imageInput">Pilih Screenshot TikTok Live</label>
                    <input 
                        type="file" 
                        name="image" 
                        id="imageInput"
                        accept="image/*"
                        required>
                    <p class="text-xs mt-1" style="color: #6b7280;">Max size: 5MB. Format: JPG, PNG, WebP</p>
                </div>

                <!-- Preview -->
                <div id="preview" class="hidden">
                    <label>Preview:</label>
                    <div class="preview">
                        <img id="previewImage" alt="Preview">
                    </div>
                </div>

                <button 
                    type="submit"
                    id="submitBtn"
                    class="btn btn-success btn-full">
                    🚀 Extract GMV
                </button>
            </form>
        </div>

        <!-- Loading -->
        <div id="loading" class="card hidden">
            <div class="loading">
                <div class="spinner"></div>
                <p style="color: #6b7280;">Memproses gambar dengan OCR...</p>
                <p class="text-sm mt-2" style="color: #9ca3af;">Ini mungkin memakan waktu 10-30 detik</p>
            </div>
        </div>

        <!-- Result -->
        <div id="result" class="card hidden">
            <h2>Hasil OCR:</h2>
            <div id="resultContent" class="result-box">
                <!-- Result will be injected here -->
            </div>
        </div>

        <!-- Quick Links -->
        <div class="flex mt-6">
            <a href="{{ route('dashboard') }}" class="btn btn-gray flex-1">
                ← Back to Dashboard
            </a>
            <a href="{{ route('test.ocr.key') }}" target="_blank" class="btn btn-primary flex-1">
                🔑 Test API Key
            </a>
        </div>
    </div>

    <script>
        // Preview image
        document.getElementById('imageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('preview').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        // Submit form
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = document.getElementById('submitBtn');
            const loading = document.getElementById('loading');
            const result = document.getElementById('result');
            
            // Show loading
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Processing...';
            loading.classList.remove('hidden');
            result.classList.add('hidden');
            
            try {
                const response = await fetch('{{ route("test.ocr.upload.post") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                
                // Display result
                document.getElementById('resultContent').innerHTML = 
                    '<pre class="text-sm">' + JSON.stringify(data, null, 2) + '</pre>';
                
                result.classList.remove('hidden');
                
                // Show alert based on result
                if (data.ocr_result?.success) {
                    alert('✅ Success!\n\nGMV Detected: Rp ' + data.ocr_result.gmv.toLocaleString('id-ID'));
                } else {
                    alert('❌ Failed\n\n' + (data.ocr_result?.message || 'Unknown error'));
                }
                
            } catch (error) {
                alert('Error: ' + error.message);
                document.getElementById('resultContent').innerHTML = 
                    '<div class="text-red-600">Error: ' + error.message + '</div>';
                result.classList.remove('hidden');
            } finally {
                // Hide loading
                loading.classList.add('hidden');
                submitBtn.disabled = false;
                submitBtn.textContent = '🚀 Extract GMV';
            }
        });
    </script>
</body>
</html>