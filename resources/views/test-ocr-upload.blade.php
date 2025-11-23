<!DOCTYPE html>
<html>
<head>
    <title>Test OCR - TikTok GMV</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        .upload-form {
            margin: 30px 0;
        }
        input[type="file"] {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            width: 100%;
            margin-bottom: 15px;
        }
        button {
            background: #0095f6;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #0077cc;
        }
        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .gmv-value {
            font-size: 32px;
            font-weight: bold;
            margin: 20px 0;
            color: #0095f6;
        }
        .raw-text {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        .image-preview {
            max-width: 100%;
            border: 2px solid #ddd;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Test OCR - TikTok GMV Detector</h1>
        
        <div class="upload-form">
            <form method="POST" action="{{ route('test.ocr.process') }}" enctype="multipart/form-data">
                @csrf
                <label>Upload Screenshot TikTok Live:</label>
                <input type="file" name="image" accept="image/*" required>
                <button type="submit">🚀 Proses OCR</button>
            </form>
        </div>

        @if(session('error'))
            <div class="result error">
                <strong>Error:</strong> {{ session('error') }}
            </div>
        @endif

        @if(isset($result))
            @if($result['success'])
                <div class="result success">
                    <h2>✅ OCR Berhasil!</h2>
                    <div class="gmv-value">
                        💰 GMV: Rp {{ number_format($result['gmv'], 0, ',', '.') }}
                    </div>
                    
                    @if(isset($result['all_numbers']) && count($result['all_numbers']) > 0)
                        <p><strong>Semua angka yang terdeteksi:</strong></p>
                        <ul>
                            @foreach($result['all_numbers'] as $num)
                                <li>Rp {{ number_format($num, 0, ',', '.') }}</li>
                            @endforeach
                        </ul>
                    @endif
                    
                    <hr>
                    <p><strong>Teks Mentah dari OCR:</strong></p>
                    <div class="raw-text">{{ $result['raw_text'] }}</div>
                </div>
            @else
                <div class="result error">
                    <h2>❌ OCR Gagal</h2>
                    <p><strong>Pesan:</strong> {{ $result['message'] }}</p>
                    
                    @if(isset($result['raw_text']))
                        <hr>
                        <p><strong>Teks yang terdeteksi:</strong></p>
                        <div class="raw-text">{{ $result['raw_text'] }}</div>
                    @endif
                </div>
            @endif
        @endif
    </div>
</body>
</html>