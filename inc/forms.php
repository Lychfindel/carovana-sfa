<?php
defined('CAROVANA') || exit;

// Definizione dei form. Le pagine li rendono automaticamente e le colonne dei
// CSV vengono ricavate da qui: per aggiungere/togliere una domanda basta
// modificare queste liste.
//   'public' => true  -> il campo compare sul sito (una volta approvato)
// Tipi: text, email, url, textarea, datetime-local, select, radio, map, iniziativa, foto

const FORM_INIZIATIVA = [
    ['name' => 'chi', 'label' => 'Chi?', 'type' => 'text', 'required' => true, 'public' => true,
     'help' => "L'associazione/osservatorio/gruppo che organizza l'iniziativa",
     'placeholder' => 'OCIO - Osservatorio CIvicO sulla casa e la residenzialità'],
    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'public' => false,
     'help' => "Un'email che possiamo usare per contattarti",
     'placeholder' => 'osservatorio@ocio-venezia.it'],
    ['name' => 'data', 'label' => 'Data', 'type' => 'datetime-local', 'required' => true, 'public' => true,
     'help' => "Quando si terrà l'iniziativa"],
    ['name' => 'citta', 'label' => 'Città', 'type' => 'text', 'required' => true, 'public' => true,
     'help' => "La città in cui vorrai organizzare l'iniziativa", 'placeholder' => 'Venezia'],
    ['name' => 'dove', 'label' => 'Dove?', 'type' => 'map', 'required' => true, 'public' => true,
     'help' => "Segna sulla mappa l'indirizzo dove verrà effettuata l'iniziativa (è sufficiente un "
             . "indirizzo approssimativo, tutte le informazioni potranno poi essere modificate!)"],
    ['name' => 'titolo', 'label' => 'Titolo', 'type' => 'text', 'required' => true, 'public' => true,
     'help' => "Il titolo dell'iniziativa"],
    ['name' => 'descrizione', 'label' => 'Breve descrizione', 'type' => 'textarea', 'required' => true,
     'public' => true, 'help' => "Una breve descrizione dell'evento",
     'placeholder' => "Descrivi l'iniziativa sia nei contenuti che nella tipologia. Non c'è nessun vincolo "
                    . "sulla forma: si può organizzare un generico incontro, un dibattito, una tavola "
                    . "rotonda o un qualsiasi altro tipo di evento."],
    ['name' => 'link', 'label' => "Link all'evento", 'type' => 'url', 'required' => false, 'public' => true,
     'help' => "Se vuoi qua puoi inserire un link all'evento",
     'placeholder' => 'https://sfa.ocio-venezia.it/'],
];

const FORM_CONTRIBUTO = [
    ['name' => 'iniziativa_id', 'label' => 'A quale iniziativa si riferisce?', 'type' => 'iniziativa',
     'required' => true, 'public' => false,
     'help' => "Scegli l'iniziativa della Carovana che hai organizzato"],
    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'public' => false,
     'help' => "La stessa email che hai usato per proporre l'iniziativa"],
    ['name' => 'info', 'label' => "Informazioni aggiuntive sull'iniziativa", 'type' => 'textarea',
     'required' => false, 'public' => true,
     'help' => "Com'è andata? Chi ha partecipato, quante persone, cosa è successo…"],
    ['name' => 'proposte', 'label' => 'Proposte, osservazioni, suggerimenti', 'type' => 'textarea',
     'required' => false, 'public' => true,
     'help' => 'Cosa è emerso sulla proposta di legge? Cosa manca, cosa cambieresti?'],
    ['name' => 'foto', 'label' => 'Foto', 'type' => 'foto', 'required' => false, 'public' => true,
     'help' => "Fino a 6 foto dell'iniziativa (JPG, PNG o WebP, massimo 10 MB ciascuna)"],
];
