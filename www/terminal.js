document.addEventListener('DOMContentLoaded', function() {
    const terminalOutput = document.getElementById('terminal-output');
    const terminalInput = document.getElementById('terminal-input');
    let step = 0;
    let name = '';
    let email = '';
    let message = '';

    const prompts = [
        'Hallo Operatør',
        'Hvis du ønsker å nå meg gjennom dypet av cyberspace, fortell meg navnet verden vil huske deg for',
        'Hvis du ønsker at jeg skal nå deg, fortell meg hvor. Gi meg sekvensen som forbinder ditt digitale jeg med virkeligheten',
        'Hva vil du fortelle meg, '
    ];

    function printPrompt() {
        terminalInput.value = ''; // Clear the input field before printing the next prompt
        let promptText = step === 3 ? `${prompts[step]}${name}?` : prompts[step];
        let index = 0;

        function typeCharacter() {
            if (index < promptText.length) {
                terminalOutput.innerHTML += promptText.charAt(index);
                index++;
                setTimeout(typeCharacter, 50); // Adjust typing speed here
            } else {
                terminalOutput.innerHTML += '<br>';
                terminalInput.focus();
            }
        }

        typeCharacter();
    }

    function handleInput() {
        const input = terminalInput.value.trim();
        if (input === '') return;

        terminalOutput.innerHTML += `<div>> ${input}</div>`;

        if (step === 0) {
            if (input === "Wake up, Neo...") {
                transformToMatrix();
                return;
            } else if (input === "The cake is a lie") {
                displayImage('media/glados.jpg');
                return;
            } else if (input === "Open the pod bay doors, HAL") {
                displayImage('media/hal.jpg');
                return;
            }
        }

        if (step === 1) {
            name = input;
        } else if (step === 2) {
            email = input;
        } else if (step === 3) {
            message = input;
            sendFormData();
            return;
        }
        step++;
        printPrompt();
    }

    function sendFormData() {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'kontakt.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                terminalOutput.innerHTML += `<div>${xhr.responseText}</div>`;
            }
        };
        const params = `name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&message=${encodeURIComponent(message)}`;
        xhr.send(params);
    }

    function transformToMatrix() {
        document.body.style.background = "url('media/matrix.gif') center center / cover no-repeat";
        document.body.style.color = "#0f0";
        terminalOutput.innerHTML = '';
        terminalInput.style.display = 'none';
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                location.reload();
            }
        });
    }

    function displayImage(imagePath) {
        document.body.style.background = `url('${imagePath}') center center / 100% 100% no-repeat`;
        terminalOutput.innerHTML = '';
        terminalInput.style.display = 'none';
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                location.reload();
            }
        });
    }

    terminalInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            handleInput();
        }
    });

    printPrompt();
});