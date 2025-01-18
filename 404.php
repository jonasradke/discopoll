<?php
include 'partials/header.php';
// 404.php
http_response_code(404); // Make sure PHP sets the real HTTP status to 404
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Page Not Found (404)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"><!-- Mobile responsive -->
</head>
<body>
    <div style="text-align: center; margin-top: 50px;">
        <h1>404 - Page Not Found</h1>
        <p>Sorry, hier fliegen keine Frisbees.</p>
        <p><a href="/">Startseite</a></p>
    </div>

<?php include 'partials/footer.php'; ?>
</body>
</html>
