<?php
/**
 * PROCEDURA DI ESPORTAZIONE ORDINE PER PRODUTTORE "NOI E LA NATURA"
 * @version 0.2
 * 
 * Valido per la versione di iGruppi <= 1.4
 */

  // set locale
  setlocale(LC_TIME,"it_IT");
  error_reporting(E_ALL);

  /**
   * Parametri di connessione al DB
   */
  $DB_HOST = 'localhost';
  $DB_USER = 'root';
  $DB_PASS = '';
  $db_name = "igruppi";

  /***
   * Idordine da passare come parametro URL
   *    -> /get_csv_noielanatura.php?idordine=xx
   */
  if(!isset($_REQUEST["idordine"])) {
      die("'idordine' parameter NOT exists!");
  }
  $idordine = $_REQUEST["idordine"];

  // si connette al DB
  $db = new mysqli ($DB_HOST, $DB_USER, $DB_PASS, $db_name);
  if ($db->connect_error) {
    die ("Connect to $db_name failed: (connect_errno=".$db->connect_errno.",connect_error=".$db->connect_error.")");
  }
  
  // check if ORDINE exists
  $sth_check = $db->prepare("SELECT * FROM ordini WHERE idordine= ?");
  $sth_check->bind_param('i',$idordine);
  $sth_check->execute();
  if (!$sth_check->fetch()) {
    die ("Prepare statement failed: errno=".$db->errno.", error=".$db->error);
  }  
  $sth_check->free_result();

  // query sull'ordine fornitore
  $query = "
  select
    ord.data_fine AS datachiusura,
    ord.idproduttore AS idfornitore,
    frn.ragsoc AS ragione
  from
    produttori       frn,
    ordini ord
  where
    frn.idproduttore = ord.idproduttore and
    ord.idordine = ?
  " ;

  $statement = $db->prepare($query) ; 
  if (!$statement) {
    die ("Prepare statement failed: errno=".$db->errno.", error=".$db->error);
  }

  $statement->bind_param('i',$idordine);
  $statement->execute();

  $statement->bind_result(
    $datachiusura,
    $idfornitore,
    $ragione
  ) ;

  $statement->fetch() ;
  $statement->free_result();


  // query sui soci
  $query = "
  SELECT
    u.iduser AS idsocio,
    u.cognome,
    u.nome,
    sum(op.costo * oup.qta) as prezzo
  FROM
    users       u,
    ordini_user_prodotti    oup,
    ordini_prodotti        op
  WHERE    
    u.iduser = oup.iduser and
    oup.idprodotto = op.idprodotto AND oup.idordine = op.idordine and
    op.idordine = ?
  group by
    u.iduser
  order by
    u.iduser
  " ;
  
  $statement = $db->prepare($query) ; 
  if (!$statement) {
    die ("Prepare statement failed: errno=".$db->errno.", error=".$db->error);
  }

  $statement->bind_param('i',$idordine);
  $statement->execute();

  $statement->bind_result(
    $idsocio,
    $cognome,
    $nome,
    $totale
  ) ;

  // costruisco l'header e l'elenco soci
  $header = "Articolo;Descrizione;Confezione;Prezzo;Cartone;" ;
  $elenco_soci = array() ;
  $elenco_totali = array() ;
  while($statement->fetch()) {
    $header .= "$cognome $nome;" ;
    $elenco_soci[$idsocio] = 0 ;
    $elenco_totali[$idsocio] = $totale ;
  }
  $header .= "Gruppo" ;

  // stampa l'intestazione
  // header('Content-Type: text/plain');
  header('Content-Type: application/octet-stream');
  header("Content-Transfer-Encoding: Binary"); 
  header("Content-disposition: attachment; filename=\"$db_name-".strtolower(preg_replace("/[^a-zA-Z]/","",$ragione))."-$idordine.csv");

  echo "GAS Iqbal Masih - $db_name - ORDINE N.$idordine DEL $datachiusura ($ragione)\n" ; 
  echo "\n" ;
  echo "$header\n" ;

  $statement->free_result();

  // query sugli item
  $query = "
  SELECT
    p.idprodotto,
    p.codice,
    p.descrizione,
    p.udm AS tipoprezzo,
    op.costo AS prezzo,
    oup.iduser AS idsocio,
    oup.qta as quantita
  FROM
    ordini_user_prodotti    oup
    JOIN ordini_prodotti op ON oup.idprodotto = op.idprodotto AND oup.idordine=op.idordine
    JOIN prodotti p ON oup.idprodotto = p.idprodotto
  WHERE  
    oup.idordine = ?
  GROUP BY
    oup.iduser, oup.idprodotto    
  ORDER BY
    oup.idprodotto, oup.iduser
  " ;
  $statement = $db->prepare($query) ; 
  if (!$statement) {
    die ("Prepare statement failed: errno=".$db->errno.", error=".$db->error);
  }

  $statement->bind_param('i',$idordine);
  $statement->execute();

  $statement->bind_result(
    $idprodotto,
    $codice,
    $descrizione,
    $tipoprezzo,
    $prezzo,
    $idsocio,
    $quantita
  ) ;

  // GOING THROUGH THE DATA

  $idprodotto_old = -32767 ;
  $codice_old = null;
  $descrizione_old = null ;
  $tipologia_old = null ;
  $tipoprezzo_old = null ;
  $prezzo_old = null ;
  $quantita_arr = $elenco_soci ;
  
//    print_r($quantita_arr);die;
    
  while ($statement->fetch()) {

    if ($idprodotto!=$idprodotto_old && $idprodotto_old>0) {

//      echo "FINE RIGA<br>";

      // costruisce la riga
      $row = "$descrizione_old;$ragione;$tipoprezzo_old;$prezzo_old;;" ;
      $sum = 0 ;
      foreach ($quantita_arr as $qta) {
        $row .= "$qta;" ; 
        $sum += $qta ;
      }
      $row .= $sum ;

      // stampa la riga
      echo "$row\n" ;

      // re-inzializza l'array delle quantit�
      $quantita_arr = $elenco_soci ;

    }
//      echo "prodotto=>$idprodotto socio=>$idsocio quantita=>$quantita <br>" ;

    $quantita_arr[$idsocio] = $quantita ;
    
    $codice_old = $codice;
    $idprodotto_old = $idprodotto;
    $descrizione_old = str_pad($codice_old, 5, "*", STR_PAD_RIGHT) . $descrizione;
    $tipoprezzo_old = $tipoprezzo;
    $prezzo_old = $prezzo;
  	  
  }

  // costruisce e stampa l'ultima riga
  $row = "$descrizione_old;$ragione;$tipoprezzo_old;$prezzo_old;;" ;
  $sum = 0 ;
  foreach ($quantita_arr as $qta) {
    $row .= "$qta;" ; 
    $sum += $qta ;
  }
  $row .= $sum ;
  echo "$row\n" ;

  // stampa i subtotali (?)
  $row = ";;;;SUBTOTALE;" ;
  $sum = 0 ;
  foreach ($elenco_totali as $totale) {
    $row .= "$totale;" ;
    $sum += $totale ;
  }
  $row .= $sum ;
  echo "$row\n" ;

  // stampa i subtot. corr. (?)
  $row = ";;;;SUBTOT. CORR.;" ;
  $sum = 0 ;
  foreach ($elenco_totali as $totale) {
    $row .= "$totale;" ;
    $sum += $totale ;
  }
  $row .= $sum ;
  echo "$row\n" ;

  // stampa le spese acc. (?)
  $row = ";;;;SPESE ACC.;" ;
  $sum = 0 ;
  foreach ($elenco_totali as $totale) {
    $spese_acc = 0 ;
    $row .= "$spese_acc;" ;
    $sum += $spese_acc ;
  }
  $row .= $sum ;
  echo "$row\n" ;

  // stampa i totali
  $row = ";;;;TOTALE;" ;
  $sum = 0 ;
  foreach ($elenco_totali as $totale) {
    $row .= "$totale;" ;
    $sum += $totale ;
  }
  $row .= $sum ;
  echo "$row\n" ;

  $statement->free_result();

  // CLOSE CONNECTION
  mysqli_close ($db);

?>
