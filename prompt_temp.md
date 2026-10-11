Voglio creare un nuovo modulo Gestione Conti per il CRM.
L’obiettivo è realizzare un sistema estremamente flessibile che permetta di gestire qualsiasi tipologia dei conti 
Categorie
Ogni prodotto dovrà appartenere ad una categoria.
Le categorie dovranno essere organizzate in una struttura gerarchica padre/figlio, consentendo quindi la creazione di un numero illimitato di sottocategorie.
Ogni categoria definirà:
un insieme di campi fissi, comuni a tutti i prodotti;
un insieme di attributi dinamici che caratterizzano quella specifica tipologia di prodotto.
Durante la creazione di una categoria dovrà essere possibile:
creare nuovi attributi dinamici;
ereditare automaticamente gli attributi della categoria padre;
aggiungere ulteriori attributi specifici della categoria corrente.
L’eredità dovrà essere ricorsiva, quindi una categoria figlia dovrà automaticamente avere a disposizione anche gli attributi definiti da tutti i suoi antenati.
Attributi dinamici
Gli attributi dinamici dovranno supportare i seguenti tipi:
STRING → input testuale libero;
INTEGER → numero intero;
DECIMAL → numero decimale;
BOOLEAN → checkbox;
ENUM → select con valori configurabili.
Per gli attributi di tipo ENUM dovrà essere possibile definire l’elenco delle opzioni disponibili direttamente durante la configurazione dell’attributo.
L’architettura dovrà essere facilmente estendibile, così da poter aggiungere nuovi tipi di attributo in futuro senza modificare la struttura esistente.
Prodotti
La scheda anagrafica del prodotto dovrà essere composta da due sezioni:
Campi generici
Campi comuni a tutti i prodotti (ad esempio codice, nome, descrizione, stato, ecc.).
Attributi dinamici
Una volta selezionata la categoria, il sistema dovrà generare automaticamente il form caricando tutti gli attributi previsti dalla categoria, inclusi quelli ereditati dalle categorie padre.
In questo modo ogni categoria potrà avere un insieme completamente diverso di attributi, mantenendo però un’unica struttura dati e un unico modulo di gestione.
Obiettivo
L’obiettivo è realizzare un sistema di prodotti completamente configurabile, in cui sia sufficiente creare una nuova categoria e definirne gli attributi per ottenere automaticamente una nuova tipologia di prodotto, senza dover sviluppare nuove tabelle, nuovi form o nuove logiche dedicate.
Prendi come riferimento l’architettura già presente nel progetto e mantieni lo stesso flusso di sviluppo utilizzato negli altri moduli del CRM.
Il modulo dovrà quindi includere:
migrations;
models;
policy e permessi;
permessi sui campi;
request validation;
services;
resources;
controller;
API REST;
AG Grid con filtri, ordinamento ed export backend-driven;
form create/edit;
gestione completa di create, edit, delete e visualizzazione;
frontend React coerente con tutti gli altri moduli del progetto.
Prendi come esempio le tabelle riportate di seguito per la progettazione del database, migliorandole se necessario qualora ritieni che possano essere rese più flessibili, scalabili o performanti.
 
Usa gli agenti per questo sviluppo
 