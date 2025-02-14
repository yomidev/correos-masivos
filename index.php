<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// Configuración de la base de datos
$host = ""; //Host de la base de datos Ejemplo: localhost 0 127.0.0.1
$dbname = "";//Nombre de la base de datos donde estarán almacenados los correos
$username = "";//Usuario de la base de datos
$password = "";//Contraseña de la base de datos

//Campos recomendados para la tabla de la base de datos: id, name, lastname, email, status
//status: 0 = No enviado, 1 = Enviado

// Conexión a la base de datos
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

//Array para cuentas de correo en caso de contener más de una cuenta para ir alternando el envío de correos
//Se recomienda tener al menos 2 cuentas para evitar bloqueos por parte del servidor de correo
//En caso de que sea solo una cuenta solo dejar un array con un solo elemento
$cuentas = [
    ['email' => 'ejemplo@mail.com', 'password' => '123'] //Sustituir por los datos de la cuenta de correo
    //Ejemplo de más cuentas
    //['email' => 'ejemplo1@mail.com', 'password' => '123']
    //['email' => 'ejemplo2@mail.com', 'password' => '123']
    //['email' => 'ejemplo3@mail.com', 'password' => '123']
];

$totalCuentas = count($cuentas);
$indiceCuenta = 0;  

//Consulta para obtener los correos a los que se enviarán los mensajes
//Se recomienda limitar la cantidad de correos a enviar por cada ejecución
$sql = "SELECT id, email FROM tabla WHERE status = '0' limit 2000";
$result = $conn->query($sql);

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}

//Función para enviar correos
function enviarCorreo($cuenta, $correoDestino, $id, $conn) {
    $mail = new PHPMailer(true);

    try {
        //Configuración del servidor de correo SMTP
        $mail->isSMTP();
        $mail->Host = '';//Servidor de correo varia según el proveedor de correo 
        $mail->SMTPAuth = true;
        $mail->Username = $cuenta['email'];//Toma los datos del array de cuentas
        $mail->Password = $cuenta['password'];//Toma los datos del array de cuentas
        $mail->SMTPSecure = 'STARTTLS';
        $mail->Port = 587; //Puerto de correo varia según el proveedor de correo

        //Configuración del correo
        $mail->setFrom($cuenta['email'], 'FOPRODE');//Toma los datos del array de cuentas y se configura el nombre del remitente
        $mail->addAddress($correoDestino);//Correo al que se enviará el mensaje tomado desde la base de datos

        
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = '';//Asunto del correo
        $mail->Body = '';//Cuerpo del correo, se pueden utilizar etiquetas HTML

        //Verifica si el correo fue enviado correctamente
        if ($mail->send()) {
            echo "Correo enviado a: $correoDestino usando {$cuenta['email']}<br>";
            $updateSQL = "UPDATE tabla SET status = '1' WHERE id = ?";//Una vez enviado el correo se actualiza el status a 1 para no volver a enviar el correo
            $stmt = $conn->prepare($updateSQL);
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                echo "Error al actualizar usuario: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Error al enviar correo a $correoDestino: " . $mail->ErrorInfo . "<br>";
        }
    } catch (Exception $e) {
        echo "Error al enviar correo a $correoDestino: " . $e->getMessage() . "<br>";
    } finally {
        
        $mail->clearAddresses();
        $mail->clearAttachments();
    }
}

//Alterna entre las cuentas de correo para enviar los mensajes
while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $correoDestino = $row['email'];
    $cuenta = $cuentas[$indiceCuenta];
    enviarCorreo($cuenta, $correoDestino, $id, $conn);
    $indiceCuenta = ($indiceCuenta + 1) % $totalCuentas;

    $delay = rand(10, 20);//Tiempo de espera entre cada correo enviado de 10 a 20 segundos
    echo "Esperando $delay segundos antes de enviar el siguiente correo...<br>";
    sleep($delay);
}

echo "Proceso finalizado.";
$conn->close();
?>