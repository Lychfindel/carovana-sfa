<?php
defined('CAROVANA') || exit;

// Definizione dei form. Le pagine li rendono automaticamente e le colonne dei
// CSV vengono ricavate da qui: per aggiungere/togliere una domanda basta
// modificare queste liste.
//   'public' => true  -> il campo compare sul sito (una volta approvato)
// Tipi: text, email, url, textarea, datetime-local, select, radio, map, iniziativa

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

// NB: il Google Form di riferimento non è pubblico; questi campi sono una
// proposta da allineare alle domande reali.
const FORM_CONTRIBUTO = [
    ['name' => 'iniziativa_id', 'label' => 'A quale iniziativa si riferisce?', 'type' => 'iniziativa',
     'required' => true, 'public' => false,
     'help' => "Scegli l'iniziativa della Carovana in cui è nato questo contributo"],
    ['name' => 'chi', 'label' => 'Chi?', 'type' => 'text', 'required' => true, 'public' => true,
     'help' => 'Il tuo nome o quello della realtà che invia il contributo'],
    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'public' => false,
     'help' => "Un'email che possiamo usare per contattarti"],
    ['name' => 'parte', 'label' => 'A quale parte della proposta di legge si riferisce?',
     'type' => 'select', 'required' => true, 'public' => true,
     'help' => 'I 10 punti del volantino della proposta',
     'options' => ['Proposta nel suo complesso',
                   '1. La casa come diritto garantito dallo Stato',
                   '2. Più case popolari, meglio mantenute',
                   '3. Affitti sociali a prezzi sostenibili',
                   '4. Regolazione affitti',
                   '5. Transizione ecologica equa',
                   '6. Stop alle speculazioni',
                   '7. Politiche abitative e rigenerazione urbana',
                   '8. Mappatura e recupero degli immobili inutilizzati',
                   '9. Riforma della fiscalità immobiliare',
                   '10. Rafforzamento della Pubblica Amministrazione',
                   'Altro']],
    ['name' => 'tipo', 'label' => 'Tipo di contributo', 'type' => 'radio', 'required' => true, 'public' => true,
     'options' => ['Modifica a una proposta esistente', 'Nuova proposta / integrazione',
                   'Critica o osservazione', 'Esperienza dal territorio']],
    ['name' => 'contributo', 'label' => 'Il contributo', 'type' => 'textarea', 'required' => true,
     'public' => true, 'help' => 'Cosa è emerso dalla discussione? Cosa manca, cosa cambieresti?'],
];
