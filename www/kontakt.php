<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontakt</title>
    <link rel="stylesheet" type="text/css" href="/cover.css">
</head>
<body>
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // EDIT THE FOLLOWING TWO LINES:
    $email_to = "magnuslyngra@live.no";
    $email_subject = "Melding fra magnuslyngra.no";

    function problem($error)
    {
        echo '<p class="error-message">Det er feil i det innsendte skjemaet. Følgende feil er oppdaget.<br><br>' . $error . '<br><br>Vennligst korriger disse feilene.</p>';
        exit();
    }

    // validation expected data exists
    if (
        !isset($_POST['name']) ||
        !isset($_POST['email']) ||
        !isset($_POST['message'])
    ) {
        problem('Det er oppdaget feil med det innsendte skjemaet.');
    }

    $name = $_POST['name']; // required
    $email = $_POST['email']; // required
    $message = $_POST['message']; // required

    $error_message = "";

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message .= 'Epostadressen du har skrevet er ugyldig.<br>';
    }

    // Validate name
    if (!preg_match("/^[A-Za-z .'-]+$/", $name)) {
        $error_message .= 'Navnet du har skrevet er ugyldig.<br>';
    }

    // Validate message
    if (strlen($message) < 2) {
        $error_message .= 'Meldingen du har skrevet er ugyldig.<br>';
    }

    if (strlen($error_message) > 0) {
        problem($error_message);
    }

    $email_message = "Skjemadetaljene følger under.\n\n";

    function clean_string($string)
    {
        $bad = array("content-type", "bcc:", "to:", "cc:", "href");
        return str_replace($bad, "", $string);
    }

    $email_message .= "Navn: " . clean_string($name) . "\n";
    $email_message .= "Epost: " . clean_string($email) . "\n";
    $email_message .= "Melding: " . clean_string($message) . "\n";

    // create email headers
    $headers = 'From: ' . $email . "\r\n" .
        'Reply-To: ' . $email . "\r\n" .
        'X-Mailer: PHP/' . phpversion();

    if (@mail($email_to, $email_subject, $email_message, $headers)) {
        echo '<p class="success-message">Takk for din henvendelse. Jeg kommer tilbake til deg så snart som råd.</p>';
    } else {
        echo '<p class="error-message">Det oppstod en feil ved sending av meldingen. Vennligst prøv igjen senere.</p>';
    }
} else {
    echo '<p class="error-message">Ugyldig forespørsel.</p>';
}
?>
</body>
</html>