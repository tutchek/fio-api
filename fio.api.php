<?php
/**
 * Copyright (c) 2013, Michal Tuláček
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 * Neither the name of Michal Tuláček nor the
 * names of its contributors may be used to endorse or promote products
 * derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL MICHAL TULÁČEK BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * FIO Api popsane v dokumentu http://www.fio.cz/docs/cz/API_Bankovnictvi.pdf
 * 
 * Umi pouze cist, neumi zapis
 */
class FioApi {

    const URL_RESET = 'https://www.fio.cz/ib_api/rest/set-last-date/%s/%s/';
    const URL_DATA = 'https://www.fio.cz/ib_api/rest/last/%s/transactions.json';

    /**
     *
     * Token pro prislusny ucet
     * @var string
     */
    protected $_token;

    /**
     * Konstruktor pro FIO API objekt
     * 
     * FIO Api pouziva pro pristup k uctu token, ktery se da vygenerovat 
     * v internetovem bankovnictvi.
     * 
     * 
     * @param string $token API fio token
     */
    public function __construct($token) {
        $this->_token = $token;
    }

    /**
     * Vynuluje casovou znacku, od ktere se stahuji data
     * 
     * 
     * @param string $resetDate Datum ke kteremu se ma vynulovat citac
     */
    public function reset($resetDate = '2000-01-01 00:00:00') {
        $date = date_create($resetDate)->format('Y-m-d');

        $url = sprintf(self::URL_RESET, $this->_token, $date);
        file_get_contents($url);
    }

    /**
     * Stahne data z banky
     * 
     * Stahne data od posledni casove znacky (da se vynulovat prikazem reset).
     * Data na vystupu jsou v nasledujicim formatu:
     * 
     * array(
     *   'iban' => iban uctu
     *   'cislo' => cislo uctu (udaj pred lomitkem)
     *   'kod_banky' => vzdy 2010
     *   'mena' => mena dle ISO 4217, tedy CZK, USD, EUR apod
     *   'transakce' => pole transakci ve formatu popsanem nize
     * )
     * 
     * Kazda transakce ma nasledujici format:
     * 
     * array(
     *   'protiucet' => cislo protiuctu (udaj pred lomitkem)
     *   'banka' => kod banky protiuctu
     *   'castka' => castka transakce, kladna = kredit, zaporna = debit
     *   'mena' => mena dle ISO 4217, tedy CZK, USD, EUR apod
     *   'ks' => konstantni symbol
     *   'vs' => variabilni symbol
     *   'ss' => specificky symbol
     *   'datum' => datum zauctovani ve formaty Y-m-d H:i:s, tedy napr. 2013/04/03 18:30:12
     *   'popis' => zprava pro prijemce
     *   'popis_interni' => uzivatelska identifikace
     *   'ident' => id transakce, unikatni v ramci uctu, pri prevodu mezi 
     *              vlastnimi ucty se muze opakovat, tedy neni samo o sobe vhodne 
     *              jako primarni klic
      'typ' => slovni vyjadreni transakce
     * )
     * 
     * @return array
     */
    public function getData() {
        $url = sprintf(self::URL_DATA, $this->_token);

        $data = file_get_contents($url);
        $cleanData = json_decode($data);

        return $this->_processData($cleanData);
    }

    protected function _processData($jsonData) {
        $accountInfo = $jsonData->accountStatement->info;

        $account = array(
            'iban' => $accountInfo->iban,
            'cislo' => $accountInfo->accountId,
            'kod_banky' => $accountInfo->bankId,
            'mena' => $accountInfo->currency,
            'transakce' => array()
        );

        $transactions = array();

        $cols = array(
            1 => 'castka',
            2 => 'protiucet',
            3 => 'kod_banky',
            4 => 'ks',
            5 => 'vs',
            6 => 'ss',
            7 => 'popis',
            8 => 'typ',
            9 => 'provedl',
            14 => 'mena',
            16 => 'zprava',
            17 => 'id_pokyn',
        );

        if (!is_array($jsonData->accountStatement->transactionList->transaction)) {
            $jsonData->accountStatement->transactionList->transaction = array();
        }

        foreach ($jsonData->accountStatement->transactionList->transaction as $t) {
            $tr = array(
                'id' => $t->column22->value,
                'datum' => date_create($t->column0->value),
            );

            foreach ($cols as $k => $v) {
                $c = $t->{'column' . $k};
                $tr[$v] = !is_null($c) ? $c->value : null;
            }

            $transactions[] = $tr;
        }

        foreach ($transactions as $transaction) {
            $t = array(
                'protiucet' => $transaction['protiucet'],
                'banka' => $transaction['kod_banky'],
                'castka' => $transaction['castka'],
                'mena' => $accountInfo->currency,
                'ks' => $transaction['ks'],
                'vs' => $transaction['vs'],
                'ss' => $transaction['ss'],
                'datum' => $transaction['datum']->format('Y-m-d H:i:s'),
                'popis' => $transaction['zprava'],
                'popis_interni' => $transaction['popis'],
                'ident' => $transaction['id'],
                'typ' => $transaction['typ']
            );

            $account['transakce'][] = $t;
        }

        return $account;
    }

}
