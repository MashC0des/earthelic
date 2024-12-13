// --------------------login/reg-----------------
function showreg(){
    const show = document.querySelector('.wrapper1')
    show.style.display = 'block'
    const hide = document.querySelector('.wrapper')
    hide.style.display = 'none'
}

function showlog(){
    const show = document.querySelector('.wrapper')
    show.style.display = 'block'
    const hide = document.querySelector('.wrapper1')
    hide.style.display = 'none'
}

// -----------------pagination--------------------
let link = document.querySelectorAll(".link")
let currentValue = 1;
function activeLink(){
    for(l of link){
        l.classList.remove("active");
    }
    event.target.classList.add("active");
    currentValue = event.target.value;
}
function backbtn(){
    if(currentValue>1){
        for(l of link){
            l.classList.remove("active");
        }
        currentValue--;
        link[currentValue-1].classList.add("active");
    }
}
function nextbtn(){
    if(currentValue<8){
        for(l of link){
            l.classList.remove("active");
        }
        currentValue++ ;
        link[currentValue-1].classList.add("active");
    }
}

