<?php

//ovo je zapravo cijela stranica pomoću koje se korisnici
//prijavljuju u sustav(1. put) i potvrđuju registraciju

require_once 'db.class.php';


//ovo provjerava u slučaju je korisnik preko linka u mailu pristupio
//dovršetku registracije
function provjeri()
{
	if( !isset($_GET['niz']) )
		return 'nastavi';

	$db = DB::getConnection();
	
	try
	{
		$st = $db->prepare( 'SELECT * FROM userList WHERE regseq=:regseq' );
		$st->execute( array( 'regseq' => $_GET['niz'] ) );
	}
	catch( PDOException $e ) { exit( 'Greška u bazi: ' . $e->getMessage() ); }

	if( $st->rowCount() === 0 )
	{
			return 'Gre&#353;ka: Nema takvog koda za registraciju!';
	}
	
	$polje = array();
	$br = 0;

	foreach( $st->fetchAll() as $row )
	{
 		if($row['hasregistered'] === 'nije' );
		{
			$polje[$br] = $row['username'];
			++$br; 
		}
	}

	if( $br === 0 )
		return 'Ve&#263; iskori&#353;ten kod.';

	for ( $i = 0; $i < $br; ++$i )
	try
	{
		$st = $db->prepare( "UPDATE userList SET hasregistered = 'jeste' WHERE username LIKE :username");
		$st->execute( array( 'username' => $polje[$i] ) );
	}
	catch( PDOException $e ) { exit( 'Greška u bazi: ' . $e->getMessage() ); }
	
	return 'ok';
}


//ovo je zapravo samo link na početnu stranicu
function povratakForma()
{ ?>
	<a href=".." > Povratak na po&#269;etnu stranicu </a>
<?php }

//poruka da zahvaljujemo na registraciji i link na početnu stranicu
function zahvala()
{ ?>
	<p style="color:green;"> Zahvaljujemo na prijavi. Provjerite e-mail za registraciju! </p>
	<hr />
<?php	povratakForma();
}

//daju formu u koju korisnik unosi podatke s kojima će se prijaviti u sustav
function forma($greska)
{ ?>
	<h1>Registrirajte se</h1> <hr />
	<?php
	if( $greska !== '' ) 
		echo '<p style="color:red">' . $greska . '</p>'; 
	?>
	<form method="post" action="<?php echo htmlentities($_SERVER['PHP_SELF']);
					?>" >
	Username (3 do 20 slova):
	<input type="text" name="username" />
	</br>
	&#352;ifra:
	<input type="password" name="password" />
	</br>
	E-mail adresa:
	<input type="text" name="email" />
	<input type="submit" value="Prijavi" />
	</form>
	<hr />
	<a href=".." > Povratak na po&#269;etnu stranicu </a>
<?php }

//provjera je li sve ispravno...
function analiziraj_POST_login()
{	
	//treba unijeti (neprazan) username
	if( !isset( $_POST['username'] ) || $_POST['username'] == '')
	{
		$greska = 'Niste unijeli username!';
		return $greska;
	}
	//treba unijeti (neprazan) password
	if( !isset( $_POST['password'] ) || $_POST['password'] == '')
	{
		$greska = 'Niste unijeli password!';
		return $greska;
	}
	//treba unijeti (neprazanu) e-mail adresu
	if( !isset( $_POST['email'] ) || $_POST['email'] == '')
	{
		$greska = 'Niste unijeli email!';
		return $greska;
	}
	//username se treba sastojati od 3 do 20 slova
	if( !preg_match( '/^[a-zA-Z]{3,20}$/', $_POST['username'] ) )
	{
		$greska = 'Username mora imati od 3 do 20 slova';
		return $greska;
	}

	//e-mail treba biti ispravan
	if( !filter_var( $_POST['email'], FILTER_VALIDATE_EMAIL) )
	{
		$greska = 'E-mail adresa nije ispravna!';
		return $greska;
	}

	$db = DB::getConnection();

	//pogledaj je li to ime slobodno
	try
	{
		$st = $db->prepare( 'SELECT * FROM userList WHERE username=:username' );
		$st->execute( array( 'username' => $_POST['username'] ) );
	}
	catch( PDOException $e ) { exit( 'Greška u bazi: ' . $e->getMessage() ); }

	if( $st->rowCount() !== 0 )
	{
			$greska = 'Username zauzet!';
			return $greska;
	}

	//stvori slučajan niz slova za dovršetak registracije (koja ide preko mail-a)
	$reg_seq = '';
	for( $i = 0; $i < 10; ++$i )
		$reg_seq .= chr( rand(0, 25) + ord( 'a' ) );

	//pošalji onda taj mail korisniku sa linkom na stranicu za dovršetak registracije 
	$to       = $_POST['email'];
	$subject  = 'Registracijski mail';
	$message  = 'Poštovani ' . $_POST['username'] . "!\nZa dovršetak registracije kliknite na sljedeći link: ";
	$message .= 'http://' . $_SERVER['SERVER_NAME'] . htmlentities( dirname( $_SERVER['PHP_SELF'] ) ) . '/registracija.php?niz=' . $reg_seq . "\n";
	$headers  = 'From: rp2@studenti.math.hr' . "\r\n" .
		    'Reply-To: rp2@studenti.math.hr' . "\r\n" .
		    'X-Mailer: PHP/' . phpversion();

	$isOK = mail($to, $subject, $message, $headers);

	if( !$isOK )
	{
		$greska = 'Ne mogu poslati mail!';
		return $greska;
	}

	//ubaci korisnika u bazu - još se nije registrirao dok ne potvrdi preko mail-a
	try
	{
	$st = $db->prepare( 'INSERT INTO userList(username, password, email, hasregistered, regseq) VALUES ' .
				                '(:username, :password, :email, :hasregistered, :reg_seq)' );		
	$st->execute( array( 	'username' => $_POST['username'], 
				'password' => password_hash( $_POST['password'], PASSWORD_DEFAULT ), 
				'email' => $_POST['email'],
				'hasregistered' => 'nije', 
				'reg_seq'  => $reg_seq ) );
	}
	catch( PDOException $e ) { exit( 'Greška u bazi: ' . $e->getMessage() ); }
	

	$greska = 'ok';
	return $greska;
}

?>
<!DOCTYPE html>
<html lang="hr">
<head>
	<meta charset="utf-8" />
	<title>Registracija</title>
</head>
<body>
	<?php
	$registracija = provjeri();

	//ako je registracija uspješno provedena, poruka u link na početnu stranicu
	if( $registracija === 'ok' )
	{
		echo '<p style="color:blue" >';
		echo '&#268;estitam, uspje&#353;no ste registrirani.';
		echo 'Vratite se na prethodnu stranicu i logirajte se.';
		echo '</p>';
		povratakForma();	
	}
	else if ( $registracija !==  'nastavi' )
	{
		echo $registracija;
		povratakForma();
	}
	else
	{
		$poruka = analiziraj_POST_login();
		if($poruka === 'prazno')
			forma('');
		else if($poruka === 'ok')
			zahvala();
		else
			forma($poruka);
	}
	?>
</body>
</html>


