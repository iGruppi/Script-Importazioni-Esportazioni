<?php





        
        $idproduttore = 7; // ROB
        
        $NEW_LINE = "\n";


        // DISATTIVO TUTTI I PRODOTTI PRESENTI IN LISTINO
        echo "UPDATE prodotti SET attivo='N', note='' WHERE idproduttore='$idproduttore';".$NEW_LINE;
        
        $row = 1;
        if (($handle = fopen("listino.csv", "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                $num = count($data);
//                echo "<p> $num fields in line $row: <br /></p>\n";
                $row++;
                
                // GET FIELDS from CSV
                $codice = $data[0];
                //$lotto = "Lotto articolo: " . $data[1];
                $descrizione = html_entity_decode($data[2], ENT_QUOTES);
                $peso = $data[3];
                $prezzo = str_replace(",", ".", $data[4]);
                $idsubcat = $data[5];
//                echo "Prezzo: $prezzo". "<br />\n";
                
                // SET MY FIELDS
                
                $sth_app = $this->getDB()->prepare("SELECT * FROM prodotti WHERE codice= :codice");
                $sth_app->execute(array('codice' => $codice));
                $prodotto = $sth_app->fetch(PDO::FETCH_OBJ);
                if( $prodotto )
                {
//                    echo "TROVATO: $codice <br />";
//                    echo "CSV: $descrizione - $prezzo<br />";
//                    echo "DB: $prodotto->descrizione - $prodotto->costo<br />";
                    
                    // Controllo VARIAZIONE PREZZO
                    if( $prezzo != $prodotto->costo )
                    {
//                        echo "<span style='color: red;'>PREZZO VARIATO!!</span><br />";
                        $var_prezzo = ", costo='$prezzo'";
                    } else {
                        $var_prezzo = "";
                    }
                    
                        // UPDATE
//                        echo "UPDATE prodotti SET attivo='S' $var_prezzo WHERE codice='$codice';".$NEW_LINE;
                    
                } else {
//                    echo "<span style='color: red;'>DESCRIZIONE: $descrizione <br />";
//                    echo "NON TROVATO ------------------->> $codice </span><br />";
//                    $newDesc = $descrizione . "($peso gr.)";
                    //echo "$idsubcat ---> $descrizione<br />";
                    
                    // INSERT
                    echo "INSERT INTO prodotti SET idproduttore='$idproduttore', idsubcat='$idsubcat', codice='$codice', descrizione='$descrizione', udm='Confezione', costo='$prezzo', attivo='S';".$NEW_LINE;
                }
                
//                echo "--<br /><br />";
            }
            fclose($handle);
            
        }        
