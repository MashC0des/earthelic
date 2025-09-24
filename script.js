// Hamburger menu animation and slide-in navigation
$(document).ready(function(){
    $(".hamburger").click(function(){
        $(this).toggleClass("active"); // animate hamburger into X

        // slide animation for nav
        if ($(".nav2-links").hasClass("open")) {
            $(".nav2-links").removeClass("open").animate({right: "-250px"}, 300);
        } else {
            $(".nav2-links").addClass("open").animate({right: "0px"}, 300);
        }
    });
});
 function toggleNav() {
        var navLinks = document.querySelector('.nav-links');
        navLinks.classList.toggle('nav-active');
    }