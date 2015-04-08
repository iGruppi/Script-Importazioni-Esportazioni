<?php

/**
 * Procedura di importazione listino RAVINALA
 * da FILE passato da Davide PIGONI secondo loro gestionale
 */
        
        
        $dsn = 'mysql:host=localhost;dbname=igruppi';
        $db = new PDO($dsn, "root", "", array());
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
        
        
        if (($handle = fopen("Listino-RAVINALA.csv", "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $num = count($data);
//                echo "<p> $num fields in line $row: <br /></p>\n";die;
                $row++;
                
                // GET FIELDS from CSV
                $codice = $data[4];
                $subcat = $data[2];
                $descrizione = addslashes($data[3]);
                $prezzo = str_replace(",", ".", $data[6]);
                $idsubcat = $mySubcategories[$subcat];
                
                // SEARCH PRODUCTS...
                if( $codice != "" ) {
                    $sth_app = $db->prepare("SELECT * FROM prodotti WHERE codice= :codice");
                    $sth_app->execute(array('codice' => $codice));
                    $prodotto = $sth_app->fetch(PDO::FETCH_OBJ);
                    if( $prodotto ) 
                    {
                        
                        $db->query("UPDATE prodotti SET attivo='S' WHERE codice='$codice'");
                        
                        if( $prezzo != $prodotto->costo ) 
                        {
//                            echo "UPDATE: $codice ( $prezzo - $prodotto->costo ) <br />";
//                            echo "CSV: $descrizione - $prezzo ($note)<br />";
//                            echo "DB: $prodotto->descrizione - $prodotto->costo<br />";

                            // UPDATE
                            $db->query("UPDATE prodotti SET costo='$prezzo' WHERE codice='$codice'");
                        }
                    } else {
//                        echo "NON TROVATO: (subcat: ".$mySubcategories[$subcat].") $codice - $descrizione<br>";
                        $nt++;
                        
//                        $newDesc = $descrizione . "($prezzo euro)";
//                        echo "$codice ---> $descrizione<br />";
                        // Fields values 
                        
                        // INSERT
                        $db->query("INSERT INTO prodotti SET idproduttore='$idproduttore', idsubcat='$idsubcat', codice='$codice', descrizione='$descrizione', udm='Confezione', costo='$prezzo'");
                    }
                }
            }
            fclose($handle);
        }        

        echo "NON TROVATI: " . $nt;
        