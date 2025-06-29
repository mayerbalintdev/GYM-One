<?php
$servername = "localhost";
$username = "root";
$password = ""; 
$dbname = "gymone";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (isset($_POST['search'])) {
    $searchQuery = $_POST['search'];

    $sql = "SELECT * FROM users WHERE CONCAT(firstname, ' ', lastname) LIKE ?";
    $stmt = $conn->prepare($sql);

    $searchQuery = "%$searchQuery%";
    $stmt->bind_param("s", $searchQuery);

    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>First Name</th><th>Last Name</th><th>Email</th></tr></thead><tbody>';
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['firstname'] . '</td>';
            echo '<td>' . $row['lastname'] . '</td>';
            echo '<td>' . $row['email'] . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No results found.</p>';
    }
    
    // Kapcsolat lezárása
    $stmt->close();
    $conn->close();
}
?>
