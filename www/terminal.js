document.addEventListener('DOMContentLoaded', function() {
    const terminalOutput = document.getElementById('terminal-output');
    const terminalInput = document.getElementById('terminal-input');
    let step = 0;
    let name = '';
    let email = '';
    let message = '';

    const prompts = [
        'Hallo Operatør',
        'Hva kalles du i cyberspace, Operatør?',
        'Hvis du ønsker at jeg skal nå deg, gi meg navnet og domenet som forbinder ditt digitale jeg med virkeligheten, ',
        'Hva vil du fortelle meg, '
    ];

    function printPrompt() {
        terminalInput.value = ''; // Clear the input field before printing the next prompt
        let promptText;
        if (step === 2) {
            promptText = `${prompts[step]}${name}`;
        } else if (step === 3) {
            promptText = `${prompts[step]}${name}?`;
        } else {
            promptText = prompts[step];
        }
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
    
        // Sanitize input to remove punctuation
        const sanitizedInput = input.replace(/[^\w\s]/gi, '').toLowerCase();
    
        if (step === 0) {
            if (sanitizedInput.includes("wake up neo")) {
                transformToMatrix();
                return;
            } else if (sanitizedInput.includes("the cake is a lie")) {
                displayImage('media/glados.jpg');
                return;
            } else if (sanitizedInput.includes("open the pod bay doors hal")) {
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