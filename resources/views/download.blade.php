<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tải Ảnh Của Bạn</title>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ========== RESET & MOBILE-SAFE BASE ========== */
        * {
            box-sizing: border-box;
        }

        html, body {
            overflow-x: hidden;
        }

        body {
            font-family: 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #fff0f5, #fff5fa, #fff9fb);
            margin: 0;
            padding: 20px;
            color: #333;
            min-height: 100vh;
            line-height: 1.6;
        }

        img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        /* ======== CONTAINER CHÍNH ======== */
        .container {
            max-width: 900px;
            margin: 20px auto;
            background: rgba(255, 255, 255, 0.9);
            padding: 25px 16px;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(255, 105, 180, 0.15);
            border: 3px solid #ffb6c1;
            backdrop-filter: blur(10px);
        }

        /* ======== TIÊU ĐỀ ======== */
        h1 {
            text-align: center;
            color: #d81b60;
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 30px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.05);
            letter-spacing: 1.5px;
            animation: pulse 2s infinite, glowPink 3s ease-in-out infinite alternate;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 0 10px;
        }

        h1::before,
        h1::after {
            content: '💖';
            animation: heartbeat 1.4s ease-in-out infinite;
        }

        h1::after {
            animation-delay: 0.7s;
        }

        /* ======== PHÂN MỤC ======== */
        h2 {
            border-bottom: 4px solid #ffb6c1;
            padding-bottom: 15px;
            margin-top: 40px;
            color: #ff69b4;
            font-weight: 700;
            font-size: 1.8rem;
            text-align: center;
            position: relative;
            display: inline-block;
            padding-left: 30px;
            padding-right: 30px;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #ff69b4, #d81b60);
            border-radius: 2px;
        }

        /* ======== GALLERY ẢNH ĐƠN ======== */
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }

        .media-item {
            border: 3px solid #ffb6c1;
            border-radius: 20px;
            overflow: hidden;
            text-align: center;
            box-shadow: 0 5px 15px rgba(255, 105, 180, 0.1);
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            background: white;
        }

        .media-item:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 12px 30px rgba(255, 105, 180, 0.2);
            border-color: #ff69b4;
        }

        .media-item img {
            width: 100%;
            display: block;
            border-radius: 16px 16px 0 0;
            transition: filter 0.3s ease;
        }

        .media-item:hover img {
            filter: brightness(1.05) contrast(1.02);
        }

        /* ======== NÚT TẢI XUỐNG ======== */
        .download-btn {
            display: inline-block;
            background: linear-gradient(135deg, #ff69b4, #d81b60);
            color: white;
            padding: 14px 30px;
            text-decoration: none;
            border-radius: 50px;
            margin: 20px 0;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(216, 27, 96, 0.3);
            border: none;
            cursor: pointer;
            text-transform: uppercase;
            width: 100%;
            max-width: 220px;
        }

        .download-btn:hover {
            background: linear-gradient(135deg, #d81b60, #c71585);
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(199, 21, 133, 0.4);
            letter-spacing: 1.5px;
        }

        .single-download-btn {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 12px;
            max-width: 110px;
            margin: 10px auto !important;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ======== ẢNH CHÍNH (GHÉP/GIF) ======== */
        .main-image-container {
            text-align: center;
            margin: 40px 0;
            padding: 25px;
            background: rgba(255, 245, 250, 0.7);
            border-radius: 25px;
            border: 3px dashed #ffb6c1;
            transition: all 0.3s ease;
        }

        .main-image-container:hover {
            background: rgba(255, 240, 248, 0.9);
            border-color: #ff69b4;
            transform: translateY(-5px);
        }

.main-image-container img {
    display: block;
    margin: 0 auto;
    max-width: 100%;
    height: auto;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(255, 105, 180, 0.15);
    transition: all 0.3s ease;
}

.main-image-container:hover img {
    transform: scale(1.03);
    box-shadow: 0 10px 30px rgba(255, 105, 180, 0.25);
}

        /* ======== TRẠNG THÁI LOADING & LỖI ======== */
        .loading, .error-message {
            text-align: center;
            padding: 50px 20px;
            border-radius: 25px;
            margin: 40px auto;
            max-width: 500px;
            border: 3px dashed #ffb6c1;
        }

        .loading {
            font-size: 1.4em;
            color: #ff69b4;
            font-weight: 600;
            background: rgba(255, 245, 250, 0.8);
            animation: pulse 1.5s infinite;
        }

        .error-message {
            color: #d81b60;
            font-weight: 700;
            background: rgba(255, 240, 245, 0.9);
            font-size: 1.1rem;
            box-shadow: 0 5px 15px rgba(216, 27, 96, 0.1);
            border-style: solid;
        }

        /* ======== RESPONSIVE ======== */
        @media (max-width: 768px) {
            .container {
                margin: 15px;
                padding: 25px 16px;
                border-radius: 20px;
            }

            h1 {
                font-size: 2.2rem;
                margin-bottom: 25px;
            }

            h2 {
                font-size: 1.5rem;
                margin-top: 35px;
            }

            .gallery {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 20px;
            }

            .main-image-container {
                margin: 30px 0;
                padding: 20px;
                border-radius: 20px;
            }

            .loading, .error-message {
                padding: 40px 15px;
                font-size: 1.3em;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.6rem;
                gap: 6px;
            }

            h1::before,
            h1::after {
                font-size: 1.2em;
            }

            h2 {
                font-size: 1.3rem;
            }

            .download-btn {
                padding: 10px 20px;
                font-size: 13px;
            }

            .gallery {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            .media-item img {
                border-radius: 12px 12px 0 0;
            }

            .single-download-btn {
                padding: 6px 10px;
                font-size: 11px;
                max-width: 100px;
            }
        }

        @media (max-width: 360px) {
            .gallery {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 1.5rem;
            }
        }

        /* ======== ANIMATIONS ======== */
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes heartbeat {
            0% { transform: scale(1); }
            14% { transform: scale(1.15); }
            28% { transform: scale(1); }
            42% { transform: scale(1.15); }
            70% { transform: scale(1); }
        }

        @keyframes glowPink {
            from {
                text-shadow: 0 0 5px rgba(255, 105, 180, 0.2);
            }
            to {
                text-shadow: 0 0 15px rgba(255, 105, 180, 0.4);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Kỷ Niệm Của Bạn</h1>
        <div id="content">
            <p class="loading">Đang tải dữ liệu...</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const idQr = params.get('id_qr');
            const contentDiv = document.getElementById('content');

            if (!idQr) {
                contentDiv.innerHTML = '<p class="error-message">Lỗi: Không tìm thấy ID QR.</p>';
                return;
            }

            // Giữ nguyên logic gọi API như cũ
            const currentPath = window.location.pathname;
            const apiPath = currentPath.substring(0, currentPath.lastIndexOf('/'));
            const apiUrl = `{{ url('/api/media/session') }}?id_qr={{ $idQr }}`;

            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.error || data.length === 0) {
                        contentDiv.innerHTML = '<p class="error-message">Không tìm thấy ảnh nào cho phiên này.</p>';
                        return;
                    }

                    let html = '';
                    const compositeImage = data.find(f => f.file_type === 'composite');
                    const gif = data.find(f => f.file_type === 'gif');
                    const singlePhotos = data.filter(f => f.file_type === 'single');

                    // Hiển thị ảnh ghép chính
                    if (compositeImage) {
                        html += `
                            <div class="main-image-container">
                                <div>
                                    <h2>Ảnh Ghép</h2>
                                </div>
                                <img src="${compositeImage.url}" alt="Ảnh ghép">
                                <a href="${compositeImage.url}" download="anh-ghep.png" class="download-btn">Tải Ảnh Ghép</a>
                            </div>
                        `;
                    }

                    // Hiển thị GIF
                    if (gif) {
                        html += `
                            <div class="main-image-container">
                                <h2>Ảnh Động (GIF)</h2>
                                <img src="${gif.url}" alt="Ảnh động GIF">
                                <a href="${gif.url}" download="anh-dong.gif" class="download-btn">Tải GIF</a>
                            </div>
                        `;
                    }

                    // Hiển thị các ảnh đơn
                    if (singlePhotos.length > 0) {
                        html += '<h2>Các Ảnh Đơn</h2><div class="gallery">';
                        singlePhotos.forEach((photo, index) => {
                            html += `
                                <div class="media-item">
                                    <img src="${photo.url}" alt="Ảnh đơn ${index + 1}">
                                    <a href="${photo.url}" download="anh-don-${index + 1}.png" class="download-btn single-download-btn">Tải Ảnh</a>
                                </div>
                            `;
                        });
                        html += '</div>';
                    }

                    contentDiv.innerHTML = html;
                })
                .catch(error => {
                    console.error('Lỗi khi lấy dữ liệu:', error);
                    contentDiv.innerHTML = '<p class="error-message">Đã xảy ra lỗi khi tải dữ liệu. Vui lòng thử lại.</p>';
                });
        });
    </script>
</body>
</html>