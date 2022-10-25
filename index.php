<?php

$method = $_SERVER["REQUEST_METHOD"];
$parsed = parse_url($_SERVER['REQUEST_URI']);
$path = $parsed['path'];


$routes = [
    'GET' => [
        '/' => 'dishTypeList',
        '/admin' => 'adminHandler',
        '/edit' => 'adminEdit',
        '/admin/uj-etel-letrehozasa' => 'adminAddDishes',
        '/admin/etel-szerkesztese' => 'editDishes',
        '/admin/etel-tipusok' => 'adminAddTypes'
        
    ],
    'POST' => [
        '/login' => 'loginHandler',
        '/logout' => 'logoutHandler',
        '/update-dish' => 'updateHandler',
        '/delete-dish' => 'deleteHandler',
        '/create-dish' => 'createDishHandler',
        '/create-dish-type' => 'createTypeHandler'
    ]
];


$handlerFunction = $routes[$method][$path] ?? "notFoundHandler";
$handlerFunction();

function dishTypeList() {
    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM dishtypes');
    $statement->execute();
    $dishTypes = $statement->fetchAll(PDO::FETCH_ASSOC);
  
    foreach($dishTypes as $index => $dishType) {
        $stmt = $pdo->prepare("SELECT * FROM dishes WHERE isActive = 1 AND dishTypeId = ?");
        $stmt->execute([$dishType['id']]);
        $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dishTypes[$index]['dishes'] = $dishes;
    }


    echo render("wrapper.phtml", [
        'content' => render('public-menu.phtml', [
            'dishTypesWithDishes' => $dishTypes
        ]),
        'isAuthorized' => isLoggedIn()
    ]); 
}
function getPathWithId($url) {
    // parse_urlrel komponenseire szedjük az url-t
    $parsed = parse_url($url);
    //megvizsgáljuk, h vannak e query paraméterek
        //ha nincsenek 
        if(!isset($parsed['query'])) {
            //visszatérünk azzal az url-rel, amit kaptunk
            return $url;
        }
        // ha létezik, akkor komponenseire szedjük. Asszociatív tömböt akarunk a query-ben szereplő kulcs érték párokkal, ehhz kell egy üres tömb
        $queryParams = []; 
        //utána meghívjuk a parse_string nevű function-t parse_str(). Első paraméter várja a stringet, amit ki akarunk vizsgálni (ez, ha több query paraméter van, akkor az összeset tartalmazza $parsed['query'], második paraméterként várja változót vár, amit a működés során fog ezt fogja manipulálni. $queryParams (ez az üres tömbünk) )
        parse_str($parsed['query'], $queryParams);

    return $parsed['path'] . "?id=" . $queryParams['id'];

}
function isLoggedIn(): bool {
    if (!isset($_COOKIE[session_name()])) { 
        return false; 
    }
    if (!isset($_SESSION)) {
        session_start();
    }
    if (!isset($_SESSION['userId'])) { 
        return false; 
    }
    return true;
}


function logoutHandler() {

    session_start();
    $params = session_get_cookie_params();
    setcookie(session_name(),  '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
    session_destroy();
    header('Location: /');
}


function adminHandler(){
    if(!isLoggedIn()) {
   echo render("wrapper.phtml", [
    'content' => render('login.phtml'),
   ]);
   return;
}
$pdo = getConnection();
$stmt = $pdo->prepare("SELECT * FROM dishes ORDER BY id DESC");
$stmt->execute();
$dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo render('admin-wrapper.phtml', [
    'content' => render('dish-list.phtml', [
        'dishes' => $dishes
    ])
    ]);
}
function adminAddDishes() {
    
   
    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM `dishtypes` ');
    $statement->execute();
    $dishTypes = $statement->fetchAll(PDO::FETCH_ASSOC);
    echo render('wrapper.phtml', [
        'content' => render('create-dish.phtml' , [
            'dishTypes' => $dishTypes
        ])
    ]);

    
}

function editDishes() {
    $dishId = $_GET['id'] ?? '';
    $pdo = getConnection();
    $statement= $pdo->prepare('SELECT * FROM dishes WHERE id = ?');
    $statement->execute([$dishId]);
    $dish = $statement->fetch(PDO::FETCH_ASSOC);
    /*var_dump($dish);
    exit;*/
    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM `dishtypes` ');
    $statement->execute();
    $dishTypes = $statement->fetchAll(PDO::FETCH_ASSOC);
   
  

    echo render('wrapper.phtml', [
        'content' => render('edit-dish.phtml', [
        'dish' => $dish,
        'dishTypes' => $dishTypes
        ])
        
    ]);
}

function updateHandler() {
    $dishId = $_GET['id'] ?? '';
    $pdo = getConnection();
    $stmt = $pdo->prepare(
        "UPDATE dishes SET
            name = ?, description = ?, price = ?, isActive = ?, dishTypeId = ?
            WHERE id = $dishId"
       
    );


    $stmt->execute([
        $_POST['name'],$_POST['description'], 
        $_POST['price'],
        (int)isset($_POST['isActive']), 
        $_POST['dishTypeId']
        
    ]);
    header('Location: /admin');
    
}

function deleteHandler(){
    $dishId = $_GET['id'] ?? '';
    $pdo = getConnection();
    $stmt = $pdo->prepare("DELETE from dishes where id = $dishId");
    $stmt->execute();
    header('Location: /admin');
}

function createDishHandler() {
$pdo = getConnection();
$stmt = $pdo->prepare(
    "INSERT INTO `dishes` 
    (`name`, `description`, `price`, `isActive`, `dishTypeId`) 
    VALUES 
    (:nev, :leiras, :ar, :aktiv, :dishTypeId);"
);


$stmt->execute([
    "nev" => $_POST['name'],
    "leiras" => $_POST['description'],
    "ar" =>  $_POST['price'],
    "aktiv" =>  (int)isset($_POST['isActive']),
    "dishTypeId" =>  $_POST['dishTypeId'],
]);

header('Location: /admin');
}
function loginHandler(){
    $pdo = getConnection();
    $statement = $pdo->prepare("SELECT * FROM users where email = ?");
    $statement->execute([$_POST["email"]]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if(!$user) {
        header('Location: /login');
        return;
    }
    //passw ell.
    $isVerified = password_verify($_POST['password'], $user["password"]);
if(!$isVerified){
    header('Loaction: /login');
    return;
}

session_start();
$_SESSION['userId'] = $user['id'];
header('Location: /admin');

}

function adminAddTypes() {
    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM `dishtypes` ');
    $statement->execute();
    $dishTypes = $statement->fetchAll(PDO::FETCH_ASSOC);
   
  
    echo render('wrapper.phtml', [
        'content' => render('dish-type-list.phtml', [
            'dishTypes' => $dishTypes
        ])
    ]);
}

function createTypeHandler() {
    $pdo = getConnection();
$stmt = $pdo->prepare(
    "INSERT INTO `dishtypes` 
    (`name`, `description`) 
    VALUES 
    (:nev, :leiras);"
);


$stmt->execute([
    "nev" => $_POST['name'],
    "leiras" => $_POST['description'],
]);

header('Location: admin/etel-tipusok');
}

function notFoundHandler(){
    echo "Ez az oldal nem található";
}


function render($filePath, $params = []): string
{
    ob_start();
    require __DIR__ . "/views/" . $filePath;
    return ob_get_clean();
}

function getConnection()
{
    return new PDO(
        'mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'],
        $_SERVER['DB_USER'],
        $_SERVER['DB_PASSWORD']
    );
}