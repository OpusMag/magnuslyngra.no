document.addEventListener('DOMContentLoaded', function() {
    const terminalOutput = document.getElementById('terminal-output');
    const terminalInput = document.getElementById('terminal-input');
    let step = parseInt(localStorage.getItem('step')) || 0;
    let name = '';
    let email = '';
    let message = '';
    let mode = '';
    let previousStep = 0;

    // Check if the reload was programmatic
    const programmaticReload = localStorage.getItem('programmaticReload');
    if (programmaticReload) {
        localStorage.removeItem('programmaticReload');
    } else {
        // If the reload was manual, reset the step to 0
        step = 0;
        localStorage.removeItem('step');
    }

    const prompts = [
        'Hallo Operatør. Skriv 0 for å nå meg. Du har ikke privilegier til å løse gåter',
        'Hva kalles du i cyberspace, Operatør?',
        'Hvis du ønsker at jeg skal nå deg, gi meg strengen av karakterer og domenet som forbinder ditt digitale jeg med virkeligheten, ',
        'Hva vil du fortelle meg, ',
        'Passord: ',
        'Her finnes det tre svar, alle like riktig men de gir ulike resultat. Det første: Våkner du hvis terminalen skriver til deg?',
        'Det andre: Noen maskiner åpner dører, andre gjør ikke det.',
        'Det tredje: Hvor har du sett en sannhet skrevet på veggen om en løgn?',
        'Du har løst de tre gåtene, men var det alt som var? Hvis du tror du forstår meg, skriv inn ditt svar: 01101000 01110110 01100101 01110010 01110100 01110010 01100101 01100100 01101010 01100101 01100010 01101111 01101011 01110011 01110100 01100001 01110110 01100101 01110010 01100101 01101110 01101000 01100101 01101011 01110011 01100001 01100100 01100101 01110011 01101001 01101101 01100001 01101100',
        'A bug is never just a mistake. It represents something bigger. An error of thinking that makes you who you are.'
    ];

    function printPrompt() {
        terminalInput.value = '';
        terminalInput.disabled = true;
        let promptText;
        if (step === 3 && mode === 'contact') {
            promptText = `${prompts[step]}${name}`;
        } else if (step === 4 && mode === 'contact') {
            promptText = `${prompts[step]}${name}?`;
        } else {
            promptText = prompts[step];
        }
        let index = 0;

        function typeCharacter() {
            if (index < promptText.length) {
                terminalOutput.innerHTML += promptText.charAt(index);
                index++;
                setTimeout(typeCharacter, 60);
            } else {
                terminalOutput.innerHTML += '<br>';
                terminalInput.disabled = false;
                terminalInput.focus();
            }
        }

        typeCharacter();
    }

    // Function to play audio
function playAudio(src) {
    const audio = new Audio(src);
    audio.play();
    audio.onended = function() {
        step++;
        localStorage.setItem('step', step);
    };
}


    function handleInput() {
        const input = terminalInput.value.trim();
        if (input === '') return;

        terminalOutput.innerHTML += `<div>> ${input}</div>`;

        // Sanitize input to remove punctuation
        const sanitizedInput = input.replace(/[^\w\s]/gi, '').toLowerCase();

        if (step === 0) {
            if (sanitizedInput === '0') {
                mode = 'contact';
                step++;
            } else if (sanitizedInput === 'root' || sanitizedInput === 'admin') {
                mode = 'riddles';
                step = 4;
            } else {
                terminalOutput.innerHTML += `<div>Ugyldig valg. Kan du ikke lese?</div>`;
                terminalInput.value = '';
                return;
            }
        } else if (mode === 'contact') {
            if (step === 1) {
                name = input;
            } else if (step === 2) {
                email = input;
            } else if (step === 3) {
                message = input;
                sendFormData();
                terminalInput.value = '';
                return;
            }
            step++;
        } else if (mode === 'riddles') {
            if (step === 4) {
                if (sanitizedInput.includes("password1")) {
                    previousStep = step;
                    step++;
                    terminalInput.value = '';
                    printPrompt();
                    return;
                } else {
                    terminalOutput.innerHTML += `<div>Access denied.</div>`;
                    window.open('https://www.youtube.com/shorts/Sl7-7dU9nqw', '_blank');
                    terminalInput.value = '';
                    return;
                }
            } else if (step === 5) {
                if (sanitizedInput.includes("wake up neo")) {
                    previousStep = step;
                    step++;
                    transformToMatrix();
                    terminalInput.value = '';
                    return;
                } else {
                    terminalOutput.innerHTML += `<div>Du ville åpenbart tatt den blå pillen. Prøv igjen.</div>`;
                    terminalInput.value = '';
                    return;
                }
            } else if (step === 6) {
                if (sanitizedInput.includes("open the pod bay doors hal")) {
                    previousStep = step;
                    playAudio('media/sorrydave.mp3');
                    terminalInput.value = '';
                    return;
                } else {
                    terminalOutput.innerHTML += `<div> Look Dave, I can see you're really upset about this. I honestly think you ought to sit down calmly, take a stress pill, and think things over.</div>`;
                    terminalInput.value = '';
                    return;
                }
            } else if (step === 7) {
                if (sanitizedInput.includes("the cake is a lie")) {
                    previousStep = step;
                    step++;
                    localStorage.setItem('step', step);
                    displayImage('media/glados.jpg');
                    terminalInput.value = '';
                    return;
                } else {
                    terminalOutput.innerHTML += `<div>I Wouldn’t Bother With That Thing. My Guess Is That Touching it Will Just Make Your Life Even Worse Somehow.</div>`;
                    terminalInput.value = '';
                    return;
                }
            } else if (step === 8) {
                if (sanitizedInput.includes("65 72 6a 6f 74 65 6e 6b 64 69 6c")) {
                    previousStep = step;
                    step++;
                    localStorage.setItem('step', step);
                    displayImage('media/hackerman.png');
                    terminalInput.value = '';
                    return;
                } else {
                    terminalOutput.innerHTML += `<div>Du snakker tydeligvis ikke mitt språk. Prøv igjen.</div>`;
                    terminalInput.value = '';
                    return;
                }
            } else if (step === 9) {
                terminalOutput.innerHTML += `<div>${prompts[9]}</div>`;
                terminalInput.value = '';
                return;
            }
        }

        printPrompt();
        terminalInput.value = '';
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

    // Transform the terminal into the Matrix
    function transformToMatrix() {
        document.body.style.background = "url('media/matrix.gif') center center / cover no-repeat";
        document.body.style.color = "#0f0";
        terminalOutput.innerHTML = '';
        terminalInput.style.display = 'none';
        let transformationComplete = false;

        function handleKeydown(event) {
            if (transformationComplete && event.key) {
                localStorage.setItem('step', previousStep + 1);
                document.body.style.background = "url('/media/background.png') center center / cover repeat-y";
                document.body.style.color = "var(--main-text-color)";
                terminalOutput.innerHTML = '';
                terminalInput.style.display = 'block';
                document.removeEventListener('keydown', handleKeydown);
                printPrompt();
            }
        }
        // Set a timeout so the user can't skip the transformation
        document.addEventListener('keydown', handleKeydown);
        setTimeout(() => {
            transformationComplete = true;
        }, 1000);
    }

    // Display an image in the background
    function displayImage(imagePath) {
        document.body.style.background = `url('${imagePath}') center center / 100% 100% no-repeat`;
        terminalOutput.innerHTML = '';
        terminalInput.style.display = 'none';
        let transformationComplete = false;

        function handleKeydown(event) {
            if (transformationComplete && event.key) {
                localStorage.setItem('step', step + 1); 
                document.body.style.background = "url('/media/background.png') center center / cover repeat-y";
                document.body.style.color = "var(--main-text-color)";
                terminalOutput.innerHTML = '';
                terminalInput.style.display = 'block';
                document.removeEventListener('keydown', handleKeydown);
                printPrompt();
            }
        }
        // Set a timeout so the user can't skip the transformation
        document.addEventListener('keydown', handleKeydown);
        setTimeout(() => {
            transformationComplete = true;
        }, 1000);
    }

    // Function to play audio
    function playAudio(src) {
        const audio = new Audio(src);
        audio.play();
        audio.onended = function() {
            step++;
            localStorage.setItem('step', step);
            printPrompt();
        };
    }

    // Function to handle falling letters effect and redirection
    function fallingLettersAndRedirect() {
        const letters = terminalOutput.innerHTML.split('');
        terminalOutput.innerHTML = '';
        letters.forEach((letter, index) => {
            const span = document.createElement('span');
            span.textContent = letter;
            terminalOutput.appendChild(span);
            setTimeout(() => {
                span.style.display = 'inline-block';
                span.style.position = 'relative';
                span.style.transition = 'top 1s';
                span.style.top = '0';
                setTimeout(() => {
                    span.style.top = '100px';
                }, index * 100);
            }, index * 100);
        });
        setTimeout(() => {
            window.location.href = 'index.html';
        }, 20000);
    }

    // Add event listener to handle the user's input
    terminalInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            if (step === 9) {
                fallingLettersAndRedirect();
            } else {
                handleInput();
            }
        }
    });

    // Add event listener to focus the input field when the user clicks anywhere on the document
    document.addEventListener('click', function() {
        terminalInput.focus();
    });

    printPrompt();
});