<?php

require __DIR__."/includes/archivos.php";
require __DIR__."/includes/token.php";
require __DIR__."/includes/vendor/autoload.php";

$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = explode("/", $_SERVER['PATH_INFO']);
$path = $pathInfo[1];

switch($path)
{
    case "registro":
        if($method == "POST")
        {
            RegistroUsuario();
        }        
    break;
    case "login":
        if($method == "POST")
        {
            LoginUsuario();
        }
    break;
    case "ingreso":
        if($method == "POST")
        {
            IngresoAuto();
        }else if($method == "GET" && empty($_GET['patente']))
        {
            MostrarIngresos();
        }else{
            $patente = $_GET['patente'];
            MostrarAuto($patente);
        }
    break;
    case "retiro":
        if($method == "GET")
        {
            $patente = $pathInfo[2];
            RetiroAuto($patente);
        }        
    break;
    case "users":
        if($method == "POST")
        {
            ActualizarUsuario();
        }
    break;
}









function RegistroUsuario()
{
    $mail = $_POST['email'];
    $type = $_POST['tipo'];
    $pass = $_POST['password'];
    $nameArray = explode('.', $_FILES["imagen"]['name']);
    $imageName = $nameArray[0];
    $imageExtension = end($nameArray);
    $source = $_FILES["imagen"]["tmp_name"];

    //Checkeos de input
    if($type == "admin" || $type == "user")
    {
        $fileData = fopen("./files/users.json", "r");
        while($json = Archivo::readFileLineJson($fileData)) //Recorre el file con datos y busca si existe un usuario con el mail indicado
        {
            if($json->email == $mail)
            {
                echo "Error: Usuario ya registrado.";
                die();
            }
        }

        //Una vez alcanzado este punto se superan las validaciones y se procede a guardar la imagen y los datos.
        $randomName = rand(100, 10000);
        $newFileName = $imageName . ' - ' . $randomName . "." . $imageExtension;
        $target = "./files/" . $newFileName;
        while(file_exists($target))
        {
            $randomName = rand(100, 10000);
            $newFileName = $imageName . ' - ' . $randomName . "." . $imageExtension;
            $target = "./files/" . $newFileName;
        }

        $datos = array(
            "email" => $mail,
            "tipo" => $type,
            "password" => $pass,
            "imagen" => $newFileName
        );

        $uploaded = move_uploaded_file($source, $target);
        if($uploaded == 1)
        {
            Archivo::saveAsJson("files/users.json", $datos); //Guarda los datos en un archivo
            echo "Usuario creado correctamente.";
        }else{
            echo "Error creando el usuario - Error al cargar la imagen.";
        }
    }else{
        echo "Error: Tipo de usuario invalido.";
    }

    fclose($fileData); //Se cierra el archivo al final.    
}

function LoginUsuario()
{
    $mail = $_POST['email'];
    $pass = $_POST['password'];

    $fileData = fopen("./files/users.json", "r");
    while($json = Archivo::readFileLineJson($fileData)) //Recorre el file con datos y busca si existe un usuario con el mail indicado
    {
        if($json->email == $mail && $json->password == $pass)
        {
            $payload = array(
                "email" => $mail,
                "tipo" => $json->tipo
            );

            echo Token::encode($payload);
            fclose($fileData);
            die();
        }
    }
    //Si llega a este punto no se encontró el usuario.
    echo "Login inválido: Verifique sus datos e ingrese nuevamente.";
    fclose($fileData);
}

function IngresoAuto()
{
    $userData = Token::decode($_SERVER['HTTP_TOKEN']);
    if($userData == null)
    {
        echo "Token inválido.";
        die();
    }
    $mail = $userData->email;
    $fechaIngreso = date("Y-m-d H:i:s");
    $patente = $_POST['patente'];

    $datos = array(
        "patente" => $patente,
        "fecha_ingreso" => $fechaIngreso,
        "fecha_egreso" => "",
        "importe" => "",
        "email" => $mail
    );

    Archivo::saveAsJson("files/autos.json", $datos);
    echo "Ingreso cargado correctamente.";
}

function RetiroAuto($_patente)
{
    $userData = Token::decode($_SERVER['HTTP_TOKEN']);
    if($userData == null)
    {
        echo "Token inválido.";
        die();
    }

    $fileData = fopen("./files/autos.json", "r");
    while($json = Archivo::readFileLineJson($fileData)) //Recorre el file con datos y busca el auto por patente
    {
        if($json->patente == $_patente)
        {
            $datosAuto = $json;
            break; //Se encontraron los datos del auto.
        }
    }
    fclose($fileData);

    if($datosAuto == null || $json == false) //No se encontró la patente
    {
        echo "No se encontró la patente indicada.";
        die();
    }

    $fechaIngreso = date_create_from_format("Y-m-d H:i:s", $datosAuto->fecha_ingreso);
    $fechaEgreso = date_create("now");
    $horasCargadas = date_diff($fechaIngreso, $fechaEgreso);

    $horasCargadas = intval($horasCargadas->format('%h')); //Devuelve la diferencia en horas
    if($horasCargadas < 4) //Menos de 4 horas paga 100
    {
        $importe = $horasCargadas * 100;
    }else if($horasCargadas >= 4 && $horasCargadas <= 12){ //Entre 4 y 12 horas paga 60
        $importe = $horasCargadas * 60;
    }else{ //Mas de 12 horas paga 30
        $importe = $horasCargadas * 30;
    }

    $fechaIngresoStr = $datosAuto->fecha_ingreso;
    $fechaEgresoStr = $fechaEgreso->format("Y-m-d H:i:s");

    $datosAuto->importe = $importe;
    $datosAuto->fecha_egreso = $fechaEgresoStr;

    $archivo = Archivo::readEntireFile("files/autos.json"); //Toma los datos del archivo

    $emptyFile = fopen("./files/autos.json", "w"); //Vacia el archivo que va a ser reescrito con los cambios
    fclose($emptyFile);
    foreach($archivo as $value) //Reescribe el archivo
    {
        if(strpos($value, $_patente)) //Busca la linea en la que se ingresó el auto para editarla.
        {
            Archivo::saveAsJson("files/autos.json", $datosAuto);
        }else{
            Archivo::saveAsJson("files/autos.json", $value);
        }
    }

    print_r("Importe: $importe \n");
    print_r("Patente: $_patente \n");
    print_r("Fecha de Ingreso: $fechaIngresoStr \n");
    print_r("Fecha de Egreso: $fechaEgresoStr \n");    
}

function MostrarIngresos()
{
    $userData = Token::decode($_SERVER['HTTP_TOKEN']);
    if($userData == null)
    {
        echo "Token inválido.";
        die();
    }

    $arrayDatos = array();
    $fileData = fopen("./files/autos.json", "r");
    while($json = Archivo::readFileLineJson($fileData)) //Recorre el file con datos
    {
        if($json->fecha_egreso == "") //Se guardan solo los autos estacionados
        {
            array_push($arrayDatos, $json);
        }
    }

    //Ordena los autos estacionados por fecha de ingreso
    usort($arrayDatos, function($a, $b) {
        return $a->fecha_ingreso <=> $b->fecha_ingreso;
    });

    print_r("Patentes Ingresadas: \n");
    foreach($arrayDatos as $value)
    {
        print_r("Patente: $value->patente - Ingreso: $value->fecha_ingreso \n");
    }
}

function MostrarAuto($_patente){
    $userData = Token::decode($_SERVER['HTTP_TOKEN']);
    if($userData == null)
    {
        echo "Token inválido.";
        die();
    }

    $fileData = fopen("./files/autos.json", "r");
    while($json = Archivo::readFileLineJson($fileData)) //Recorre el file con datos y busca el auto por patente
    {
        if($json->patente == $_patente)
        {
            $datosAuto = $json;
            break; //Se encontraron los datos del auto.
        }
    }
    fclose($fileData);

    print_r("Importe: $datosAuto->importe \n");
    print_r("Patente: $_patente \n");
    print_r("Fecha de Ingreso: $datosAuto->fecha_ingreso \n");
    print_r("Fecha de Egreso: $datosAuto->fecha_egreso \n");   
}

function ActualizarUsuario()
{
    $userData = Token::decode($_SERVER['HTTP_TOKEN']);
    if($userData == null)
    {
        echo "Token inválido.";
        die();
    }

    //Proceso de Creación del archivo
    $nameArray = explode('.', $_FILES["imagen"]['name']);
    $imageName = $nameArray[0];
    $imageExtension = end($nameArray);
    $source = $_FILES["imagen"]["tmp_name"];

    $randomName = rand(100, 10000);
    $newFileName = $imageName . ' - ' . $randomName . "." . $imageExtension;
    $target = "./files/" . $newFileName;
    while(file_exists($target))
    {
        $randomName = rand(100, 10000);
        $newFileName = $imageName . ' - ' . $randomName . "." . $imageExtension;
        $target = "./files/" . $newFileName;
    }
    //Actualiza los datos del archivo:
    $archivo = Archivo::readEntireJson("files/users.json"); //Toma los datos del archivo

    $emptyFile = fopen("./files/users.json", "w"); //Vacia el archivo que va a ser reescrito con los cambios
    fclose($emptyFile);
    $uploaded = move_uploaded_file($source, $target); //Sube el nuevo archivo
    if($uploaded == 1)
    {
        foreach($archivo as $value) //Reescribe el archivo
        {
            rename("./files/$value->imagen", "./files/backups/$value->imagen"); //Mueve la imagen anterior
            if($value->email == $userData->email) //Busca la linea en la que se ingresó el auto para editarla.
            {
                $value->imagen = $newFileName;                
            }
            Archivo::saveAsJson("files/users.json", $value);
        }
        echo "Usuario actualizado correctamente.";
    }    
}

?>