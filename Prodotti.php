<?php
/**
 * Description of Controller_Prodotti
 *
 * @author gullo
 */
class Controller_Prodotti extends MyFw_Controller {



/*
 * PROCEDURA DI IMPORTAZIONE LISTINO ROB
 * 
 *      Pulire il file Excel (nascondi tutti i campi, lascia solo: codice, descrizione, peso e prezzo)
 *      Poi verificare con un copia del database aggiornato quali prodotti esistono già nel DB (rimuovere tutti i commenti dagli echo qui sotto)
 *      Eventualmente aggiornare il CSV sui codici errati (ce ne sono diversi)
 *      
 *      Questa procedura genera l'elenco delle query da lanciare poi sul db:
 *          1 - disabilita tutti i prodotti (attivo='N')
 *          2 - aggiorna i prezzi per i prodotti esistenti (solo se è variato)
 *          3 - inserisci i nuovi prodotti (il controllo viene effettuato sul codice - vanno verificati!)
 * 
 */
    function importlistinoAction()
    {
        $layout = Zend_Registry::get("layout");
        $layout->disableDisplay();
        
        $idproduttore = 7;
        
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
    }


    
    
/*
 *  PROCEDURA DI IMPORTAZIONE LISTINO RAVINALA
 *  I codici sono buoni quindi dopo aver pulito il file eseguire l'importazione
 *  Poi resta solo da impostare "idsubcat" per i nuovi prodotti
 * 
 */
    function importlistinoAction()
    {
        $layout = Zend_Registry::get("layout");
        $layout->disableDisplay();
        
        $pObj = new Model_Prodotti();
        
        $idproduttore = 10;
        
        // DISATTIVO TUTTI I PRODOTTI PRESENTI IN LISTINO
        $this->getDB()->query("UPDATE prodotti SET attivo='N' WHERE idproduttore='$idproduttore'");        
        
        $row = 1;
        if (($handle = fopen("listino-RAVINALA.csv", "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                $num = count($data);
//                echo "<p> $num fields in line $row: <br /></p>\n";
                $row++;
                
                // GET FIELDS from CSV
                $codice = $data[0];
                //$lotto = "Lotto articolo: " . $data[1];
                $descrizione = addslashes($data[1]);
                $prezzo = str_replace(",", ".", $data[5]);
                //$iva = $data[6];
                $note = "Fornitore: " . addslashes($data[7]);
                $idsubcat = $data[8];
//                echo "Prezzo: $prezzo". "<br />\n";
                
                // SEARCH PRODUCTS...
                if( $codice != "" ) {
                    $sth_app = $this->getDB()->prepare("SELECT * FROM prodotti WHERE codice= :codice");
                    $sth_app->execute(array('codice' => $codice));
                    $prodotto = $sth_app->fetch(PDO::FETCH_OBJ);
                    if( $prodotto ) {
                        
                        $this->getDB()->query("UPDATE prodotti SET attivo='S', note='$note' WHERE codice='$codice'");
                        if( $prezzo != $prodotto->costo ) {
                            
                            echo "UPDATE: $codice ( $prezzo - $prodotto->costo ) <br />";
//                            echo "CSV: $descrizione - $prezzo ($note)<br />";
//                            echo "DB: $prodotto->descrizione - $prodotto->costo<br />";

                            // UPDATE
                            $this->getDB()->query("UPDATE prodotti SET costo='$prezzo' WHERE codice='$codice'");
                        }
                    } else {
                        echo "<h4>NON TROVATO ------------------->> (subcat: $idsubcat) $codice - $descrizione</h4>";
//                        $newDesc = $descrizione . "($prezzo euro)";
//                        echo "$codice ---> $descrizione<br />";

                        // INSERT
                        $this->getDB()->query("INSERT INTO prodotti SET idproduttore='$idproduttore', idsubcat='$idsubcat', codice='$codice', descrizione='$descrizione', udm='Confezione', costo='$prezzo', note='$note'");
                    }
                }                
            }
            fclose($handle);
        }        
        exit;
    }
    
}
?>
