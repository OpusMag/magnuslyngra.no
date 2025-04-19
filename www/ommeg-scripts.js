document.addEventListener('DOMContentLoaded', function() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    const iconElement = navbarToggler ? navbarToggler.querySelector('i') : null;
    const collapseElement = document.querySelector('#navbarNav');
    if (!navbarToggler || !iconElement || !collapseElement) {
        console.error('Required elements not found');
        return;
    }
    navbarToggler.addEventListener('click', function() {
        iconElement.classList.toggle('fa-bars');
        iconElement.classList.toggle('fa-times');
        navbarToggler.classList.toggle('toggled');
    });
});

var slideIndex = 0;
function showSlides() {
    const slides = document.getElementsByClassName("mySlides");
    for (let i = 0; i < slides.length; i++) {
        slides[i].style.display = "none";
    }
    slideIndex++;
    if (slideIndex > slides.length) { slideIndex = 1; }
    slides[slideIndex-1].style.display = "block";
    setTimeout(showSlides, 8000);
}
document.addEventListener('DOMContentLoaded', function() {
    showSlides();

    const video1 = document.getElementById('myVideo');
    const video2 = document.getElementById('myVideo1');

    if (video1) {
        video1.addEventListener('click', function() {
            if (this.requestFullscreen) {
                this.requestFullscreen();
            } else if (this.mozRequestFullScreen) {
                this.mozRequestFullScreen();
            } else if (this.webkitRequestFullscreen) {
                this.webkitRequestFullscreen();
            } else if (this.msRequestFullscreen) {
                this.msRequestFullscreen();
            }
        });
    }

    if (video2) {
        video2.addEventListener('click', function() {
            if (this.requestFullscreen) {
                this.requestFullscreen();
            } else if (this.mozRequestFullScreen) {
                this.mozRequestFullScreen();
            } else if (this.webkitRequestFullscreen) {
                this.webkitRequestFullscreen();
            } else if (this.msRequestFullscreen) {
                this.msRequestFullscreen();
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const textWrapper = document.querySelector('.text-wrapper1');
    const imageOverlay = document.getElementById('image-overlay');
    const overlayImage = document.getElementById('overlay-image');
    
    if (textWrapper && imageOverlay && overlayImage) {
        textWrapper.addEventListener('click', function() {
            overlayImage.src = '/media/edb.jpg';
            imageOverlay.style.display = 'block';
        });
        
        function closeOverlay() {
            imageOverlay.style.display = 'none';
        }
        
        imageOverlay.addEventListener('click', function(event) {
            if (event.target === imageOverlay) {
                closeOverlay();
            }
        });
        
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeOverlay();
            }
        });
    } else {
        console.error('Required elements not found');
    }
});