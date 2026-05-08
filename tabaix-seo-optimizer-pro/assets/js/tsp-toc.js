document.addEventListener('DOMContentLoaded', function() {
    // Smooth scroll for TOC links
    document.querySelectorAll('.tsp-toc-list a').forEach(function(a) {
        a.addEventListener('click', function(e) {
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
    // Toggle collapse
    document.querySelectorAll('.tsp-toc-title').forEach(function(title) {
        title.addEventListener('click', function() {
            var list = this.nextElementSibling;
            var btn  = this.querySelector('.tsp-toc-toggle');
            if (list) {
                list.style.display = list.style.display === 'none' ? '' : 'none';
                if (btn) btn.textContent = list.style.display === 'none' ? '▶' : '▼';
            }
        });
    });
    // Back to top button
    var btn = document.getElementById('tsp-back-top');
    if (btn) {
        window.addEventListener('scroll', function() {
            btn.style.display = window.scrollY > 400 ? 'flex' : 'none';
        });
        btn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
});
