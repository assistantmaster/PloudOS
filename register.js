const form = document.querySelector("form");
const password = document.getElementById("pw1");
const confirmPassword = document.getElementById("pw2");
const message = document.getElementById("pw_return");

form.addEventListener("submit", function(e) {
    if (password.value !== confirmPassword.value) {
        e.preventDefault();
        message.innerText = "Passwörter stimmen nicht überein";
    }
});

function checkPasswords() {
    if (password.value !== "" && confirmPassword.value !== "") {
        if (password.value === confirmPassword.value) {
            message.innerText = "";
        } else {
            message.innerText = "Passwörter stimmen nicht überein";
        }
    }
}
password.addEventListener("input", checkPasswords);
confirmPassword.addEventListener("input", checkPasswords);
