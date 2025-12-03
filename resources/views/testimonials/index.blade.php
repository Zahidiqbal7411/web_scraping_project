<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 50px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .testimonials-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section-title {
            text-align: center;
            color: #333;
            margin-bottom: 50px;
        }

        .section-title h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .section-title p {
            font-size: 1.1rem;
            color: #666;
        }

        .testimonials-wrapper {
            margin-top: 40px;
        }

        .testimonial-item {
            display: flex;
            background: #fff;
            border-radius: 0;
            margin-bottom: 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            min-height: 500px;
        }

        .testimonial-image-section {
            flex: 0 0 45%;
            background: #2c3e50;
            position: relative;
            overflow: hidden;
        }

        .testimonial-image-section img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .testimonial-image-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 100px;
            font-weight: bold;
        }

        .testimonial-content-section {
            flex: 1;
            padding: 60px 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #fff;
            position: relative;
        }

        .quote-icon {
            font-size: 80px;
            color: #3b82f6;
            margin-bottom: 30px;
            line-height: 1;
        }

        .testimonial-quote {
            font-size: 1.3rem;
            line-height: 1.8;
            color: #2c3e50;
            font-style: italic;
            margin-bottom: 40px;
            font-weight: 400;
        }

        .testimonial-author-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .author-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .author-avatar-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 24px;
            font-weight: bold;
        }

        .author-details {
            flex: 1;
        }

        .author-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .author-position {
            font-size: 0.95rem;
            color: #3b82f6;
            font-weight: 500;
        }

        .testimonial-navigation {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
        }

        .nav-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #d1d5db;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nav-dot.active {
            background: #3b82f6;
            width: 30px;
            border-radius: 6px;
        }

        .nav-dot:hover {
            background: #9ca3af;
        }

        @media (max-width: 992px) {
            .testimonial-item {
                flex-direction: column;
                min-height: auto;
            }

            .testimonial-image-section {
                flex: 0 0 300px;
            }

            .testimonial-content-section {
                padding: 40px 30px;
            }

            .testimonial-quote {
                font-size: 1.1rem;
            }
        }

        .no-testimonials {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .no-testimonials i {
            font-size: 60px;
            color: #667eea;
            margin-bottom: 20px;
        }

        .no-testimonials h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .no-testimonials p {
            color: #666;
        }

        .loading {
            text-align: center;
            color: #fff;
            font-size: 1.2rem;
            padding: 40px;
        }

        .loading i {
            font-size: 40px;
            margin-bottom: 15px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="testimonials-container">
        <div class="section-title">
            <h1><i class="fas fa-comments"></i>Web techfusion Scraping</h1>
            
            <div style="text-align: left; margin-top: 20px;">
                <button id="syncBtn" class="btn btn-primary" onclick="syncTestimonials()">
                    <i class="fas fa-sync-alt"></i> Sync Testimonials
                </button>
            </div>
        </div>

        <div id="testimonials-content">
            <div class="loading">
                <div><i class="fas fa-spinner"></i></div>
                <div>Loading testimonials...</div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentTestimonialIndex = 0;
        let testimonialsData = [];

        // Sync testimonials from source website
        async function syncTestimonials() {
            const syncBtn = document.getElementById('syncBtn');
            const originalText = syncBtn.innerHTML;
            
            try {
                // Show loading state
                syncBtn.disabled = true;
                syncBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
                
                const response = await fetch('{{ url("/sync-testimonials") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Check if any updates were found
                    if (data.updated === false) {
                        // No new updates
                        syncBtn.innerHTML = '<i class="fas fa-info-circle"></i> No Updates Found';
                        syncBtn.classList.remove('btn-primary');
                        syncBtn.classList.add('btn-info');
                        
                        // Reset button after 2 seconds
                        setTimeout(() => {
                            syncBtn.innerHTML = originalText;
                            syncBtn.classList.remove('btn-info');
                            syncBtn.classList.add('btn-primary');
                            syncBtn.disabled = false;
                        }, 2000);
                    } else {
                        // Show success message
                        syncBtn.innerHTML = '<i class="fas fa-check"></i> Synced Successfully!';
                        syncBtn.classList.remove('btn-primary');
                        syncBtn.classList.add('btn-success');
                        
                        // Reload testimonials
                        await loadTestimonials();
                        
                        // Reset button after 2 seconds
                        setTimeout(() => {
                            syncBtn.innerHTML = originalText;
                            syncBtn.classList.remove('btn-success');
                            syncBtn.classList.add('btn-primary');
                            syncBtn.disabled = false;
                        }, 2000);
                    }
                } else {
                    throw new Error(data.error || 'Sync failed');
                }
            } catch (error) {
                console.error('Error syncing testimonials:', error);
                syncBtn.innerHTML = '<i class="fas fa-times"></i> Sync Failed';
                syncBtn.classList.remove('btn-primary');
                syncBtn.classList.add('btn-danger');
                
                setTimeout(() => {
                    syncBtn.innerHTML = originalText;
                    syncBtn.classList.remove('btn-danger');
                    syncBtn.classList.add('btn-primary');
                    syncBtn.disabled = false;
                }, 2000);
            }
        }

        // Fetch and display testimonials from database
        async function loadTestimonials() {
            try {
                const response = await fetch('{{ url("/api/testimonials") }}');
                const data = await response.json();
                
                const contentDiv = document.getElementById('testimonials-content');
                
                if (data.success && data.testimonials && data.testimonials.length > 0) {
                    testimonialsData = data.testimonials;
                    renderTestimonials();
                } else {
                    contentDiv.innerHTML = `
                        <div class="no-testimonials">
                            <i class="fas fa-exclamation-circle"></i>
                            <h3>No Testimonials Found</h3>
                            <p>We couldn't find any testimonials at the moment.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading testimonials:', error);
                document.getElementById('testimonials-content').innerHTML = `
                    <div class="no-testimonials">
                        <i class="fas fa-times-circle"></i>
                        <h3>Error Loading Testimonials</h3>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }

        function renderTestimonials() {
            const contentDiv = document.getElementById('testimonials-content');
            let html = '<div class="testimonials-wrapper">';
            
            // Render all testimonials but only show the current one
            testimonialsData.forEach((testimonial, index) => {
                const initial = testimonial.author_name ? testimonial.author_name.charAt(0).toUpperCase() : '?';
                
                // Use large_image if available, otherwise use author_image, otherwise use placeholder
                const mainImageHtml = testimonial.large_image
                    ? `<img src="${testimonial.large_image}" alt="${testimonial.author_name || 'Client'}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                       <div class="testimonial-image-placeholder" style="display:none;">${initial}</div>`
                    : testimonial.author_image 
                        ? `<img src="${testimonial.author_image}" alt="${testimonial.author_name || 'Client'}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                           <div class="testimonial-image-placeholder" style="display:none;">${initial}</div>`
                        : `<div class="testimonial-image-placeholder">${initial}</div>`;

                const avatarHtml = testimonial.author_image 
                    ? `<img src="${testimonial.author_image}" alt="${testimonial.author_name || 'Client'}" class="author-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                       <div class="author-avatar-placeholder" style="display:none;">${initial}</div>`
                    : `<div class="author-avatar-placeholder">${initial}</div>`;
                
                // Only show the first testimonial initially, hide others
                const displayStyle = index === 0 ? 'flex' : 'none';
                
                html += `
                    <div class="testimonial-item" style="display: ${displayStyle};" data-index="${index}">
                        <div class="testimonial-image-section">
                            ${mainImageHtml}
                        </div>
                        <div class="testimonial-content-section">
                            <div class="quote-icon">"</div>
                            ${testimonial.quote ? `<div class="testimonial-quote">${testimonial.quote}</div>` : '<div class="testimonial-quote">No testimonial text available.</div>'}
                            <div class="testimonial-author-info">
                                ${avatarHtml}
                                <div class="author-details">
                                    <div class="author-name">${testimonial.author_name || 'Anonymous'}</div>
                                    ${testimonial.author_position ? `<div class="author-position">${testimonial.author_position}</div>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            
            // Add navigation dots
            if (testimonialsData.length > 1) {
                html += '<div class="testimonial-navigation">';
                for (let i = 0; i < testimonialsData.length; i++) {
                    html += `<div class="nav-dot ${i === 0 ? 'active' : ''}" onclick="showTestimonial(${i})"></div>`;
                }
                html += '</div>';
            }
            
            contentDiv.innerHTML = html;
        }

        function showTestimonial(index) {
            // Hide all testimonials
            const testimonialItems = document.querySelectorAll('.testimonial-item');
            testimonialItems.forEach((item, i) => {
                item.style.display = i === index ? 'flex' : 'none';
            });
            
            // Update navigation dots
            const dots = document.querySelectorAll('.nav-dot');
            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === index);
            });
            
            currentTestimonialIndex = index;
        }

        // Load testimonials when page loads
        document.addEventListener('DOMContentLoaded', loadTestimonials);
    </script>
</body>
</html>
