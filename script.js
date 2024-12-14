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
function seterror(id, error)
{
    element=document.getElementById(id);
    element.getElementsByClassName('formerror')[0].innerHTML=  error;

}
function validateform(){
    var returnval=true;

    var name=document.forms['form2']['fname'].value;
    var mail=document.forms['form2']['email'].value;
    var passw=document.forms['form2']['password'].value;
    var phone1=document.forms['form2']['phone'].value;
    if(name.length<5)
    {
        seterror("name","*length of name is to short");
        returnval=false;
    }
    if(mail!= /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/)
    {
        seterror("email","*invalid Email ID");
        returnval=false;
    }
    if(passw!= /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@.#$!%*?&])[A-Za-z\d@.#$!%*?&]{8,15}$/)
    {
        seterror("pass","* invalid password ");
        returnval=false;

    }  
    if(phone1.length!=10 && phone1!= /^(\+?\d{1,3}[-.\s]?)?(\(?\d{3}\)?[-.\s]?)?\d{3}[-.\s]?\d{4}$/)
    {
        seterror("phonenum","* invalid phone number");
        returnval=false;
    }   

    return returnval;

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

