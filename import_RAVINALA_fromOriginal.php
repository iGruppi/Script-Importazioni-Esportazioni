<?php

/**
 * Procedura di importazione da listino originale RAVINALA
 * ——> ovviamente dopo averlo sistemato perché è davvero incasinato! ;-)
 */
        
        
        // LOCAL
        $dsn = 'mysql:host=localhost;dbname=igruppi_iqbal';
        $userDB = "root";
        $passwd = "";

        $db = new PDO($dsn, $userDB, $passwd, array());
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

        $idproduttore = 10; // RAVINALA
        
        // DISATTIVO TUTTI I PRODOTTI PRESENTI IN LISTINO
        $db->query("UPDATE prodotti SET attivo='N' WHERE idproduttore='$idproduttore'");        
        
        $row = 1;
        $nt = 0;
        $subcategorie = array();
        
        // corrispondenze subcat
        $mySubcategories = array(
            'Spezie' => 83, 
            'Dolci/Snack' => 86,
            'Bevande' => 85,
            'Vino/Birra' => 85,
            'Pasta' => 79,
            'Varie' => 82,
            'Cosmetici' => 82,
            'Frutta' => 86,
            'Cereali/Legumi' => 79,
            'Riso' => 79,
            'Alcolici' => 85
        );
        
        
        if (($handle = fopen("ListinoRavinalaOriginal.csv", "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $num = count($data);
//                echo "<p> $num fields in line $row: <br /></p>\n";die;
                $row++;
                
                // GET FIELDS from CSV
                $codice = str_replace("'", "", $data[1]);
                //$subcat = $data[2];
                $descrizione = htmlentities($data[2], ENT_QUOTES);
                $prezzoText = str_replace(",", ".", $data[3]);
                $prezzo = round($prezzoText, 2);
                $noteOriginal = htmlentities($data[5], ENT_QUOTES);
                $fornitorenote = htmlentities($data[6], ENT_QUOTES);
                $note = $fornitorenote . (($noteOriginal != "") ? " - ".$noteOriginal : "" );

                // SEARCH PRODUCTS...
                if( $codice != "" ) {
                    $sth_app = $db->prepare("SELECT * FROM prodotti WHERE codice= :codice");
                    $sth_app->execute(array('codice' => $codice));
                    $prodotto = $sth_app->fetch(PDO::FETCH_OBJ);
                    
                    // TRY with Codice with 0
                    if($prodotto === false) {
                        $codiceSenzaZero = substr($codice, 1);
                        $sth_app0 = $db->prepare("SELECT * FROM prodotti WHERE codice= :codice");
                        $sth_app0->execute(array('codice' => $codiceSenzaZero));
                        $prodotto = $sth_app0->fetch(PDO::FETCH_OBJ);
                    }
                    
                    if( $prodotto ) 
                    {
                        
                        $db->query("UPDATE prodotti SET attivo='S', note='$note' WHERE codice='$codice'");
                        
                        if( $prezzo != $prodotto->costo ) 
                        {
                            // echo "UPDATE: $codice ( $prezzo - $prodotto->costo ) <br />";
                            //echo "CSV: $descrizione - $prezzo ($note)<br />";
                            //echo "DB: $prodotto->descrizione - $prodotto->costo<br />";

                            // UPDATE
                            $db->query("UPDATE prodotti SET costo='$prezzo' WHERE codice='$codice'");
                        }
                    } else {
                        echo "<b>NON TROVATO</b>: $codice - $descrizione<br />";
                        $nt++;
                        // Fields values 
                        
                        // INSERT
                        $db->query("INSERT INTO prodotti SET idproduttore='$idproduttore', idsubcat=180, codice='$codice', descrizione='$descrizione', udm='Confezione', costo='$prezzo', note='$note'");
                    }
                }
            }
            fclose($handle);
        }        

        echo "NON TROVATI: " . $nt;
        