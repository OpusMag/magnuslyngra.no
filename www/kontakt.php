<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontakt</title>
    <link rel="stylesheet" type="text/css" href="/cover.css">
</head>
<body>
    <div class="terminal" id="terminal">
        <div id="terminal-output"></div>
        <input type="text" class="terminal-input" id="terminal-input" autofocus>
    </div>
    <script src="terminal.js"></script>
</body>
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_to = "magnuslyngra@live.no";
    $email_subject = "Melding fra magnuslyngra.no";

    function problem($error)
    {
        echo 'Det er feil i det innsendte skjemaet. Følgende feil er oppdaget.<br><br>' . htmlspecialchars($error) . '<br><br>Vennligst korriger disse feilene.';
        exit();
    }

    if (
        !isset($_POST['name']) ||
        !isset($_POST['email']) ||
        !isset($_POST['message'])
    ) {
        problem('Det er oppdaget feil med det innsendte skjemaet.');
    }

    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

    $error_message = "";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message .= 'Epostadressen du har skrevet er ugyldig.<br>';
    }

    if (!preg_match("/^[A-Za-z .'-]+$/", $name)) {
        $error_message .= 'Navnet du har skrevet er ugyldig.<br>';
    }

    if (strlen($message) < 2) {
        $error_message .= 'Meldingen du har skrevet er ugyldig.<br>';
    }

    if (strlen($error_message) > 0) {
        problem($error_message);
    }

    $email_message = "Skjemadetaljene følger under.\n\n";
    $email_message .= "Navn: " . htmlspecialchars($name) . "\n";
    $email_message .= "Epost: " . htmlspecialchars($email) . "\n";
    $email_message .= "Melding: " . htmlspecialchars($message) . "\n";

    $headers = 'From: ' . $email . "\r\n" .
        'Reply-To: ' . $email . "\r\n" .
        'X-Mailer: PHP/' . phpversion();

    if (mail($email_to, $email_subject, $email_message, $headers)) {
        echo 'Takk for din henvendelse. Jeg kommer tilbake til deg så snart som råd.';
    } else {
        echo 'Det oppstod en feil ved sending av meldingen. Vennligst prøv igjen senere.';
    }
} else {
    echo 'Ugyldig forespørsel.';
}
?>
</html>