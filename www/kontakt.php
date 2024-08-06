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
// Check if the form was submitted via POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Define the recipient email and subject
    $email_to = "magnuslyngra@live.no";
    $email_subject = "Melding fra magnuslyngra.no";

    // Function to handle errors and display a message
    function problem($error)
    {
        echo '<p class="error-message">Det er feil i det innsendte skjemaet. Følgende feil er oppdaget.<br><br>' . htmlspecialchars($error) . '<br><br>Vennligst korriger disse feilene.</p>';
        exit();
    }

    // Check if required form fields are set
    if (
        !isset($_POST['name']) ||
        !isset($_POST['email']) ||
        !isset($_POST['message'])
    ) {
        problem('Det er oppdaget feil med det innsendte skjemaet.');
    }

    // Sanitize user inputs
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

    // Initialize error message variable
    $error_message = "";

    // Validate email address
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message .= 'Epostadressen du har skrevet er ugyldig.<br>';
    }

    // Validate name using a regular expression
    if (!preg_match("/^[A-Za-z .'-]+$/", $name)) {
        $error_message .= 'Navnet du har skrevet er ugyldig.<br>';
    }

    // Validate message length
    if (strlen($message) < 2) {
        $error_message .= 'Meldingen du har skrevet er ugyldig.<br>';
    }

    // If there are validation errors, display them
    if (strlen($error_message) > 0) {
        problem($error_message);
    }

    // Create the email message
    $email_message = "Skjemadetaljene følger under.\n\n";
    $email_message .= "Navn: " . htmlspecialchars($name) . "\n";
    $email_message .= "Epost: " . htmlspecialchars($email) . "\n";
    $email_message .= "Melding: " . htmlspecialchars($message) . "\n";

    // Set email headers
    $headers = 'From: ' . $email . "\r\n" .
        'Reply-To: ' . $email . "\r\n" .
        'X-Mailer: PHP/' . phpversion();

    // Send the email and check if it was successful
    if (mail($email_to, $email_subject, $email_message, $headers)) {
        echo '<p class="success-message">Takk for din henvendelse. Jeg kommer tilbake til deg så snart som råd.</p>';
    } else {
        echo '<p class="error-message">Det oppstod en feil ved sending av meldingen. Vennligst prøv igjen senere.</p>';
    }
} else {
    // Display an error message if the request method is not POST
    echo '<p class="error-message">Ugyldig forespørsel.</p>';
}
?>
</body>
</html>