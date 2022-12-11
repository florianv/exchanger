<?php

declare(strict_types=1);

/*
 * This file is part of Exchanger.
 *
 * (c) Florian Voutzinos <florian@voutzinos.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger\Service;

/**
 * Holds services.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class Registry
{
    /**
     * Returns a map of all supported services.
     */
    public static function getServices(): array
    {
        return [
            'bulgarian_national_bank' => BulgarianNationalBank::class,
            'central_bank_of_czech_republic' => CentralBankOfCzechRepublic::class,
            'central_bank_of_republic_turkey' => CentralBankOfRepublicTurkey::class,
            'central_bank_of_republic_uzbekistan' => CentralBankOfRepublicUzbekistan::class,
            'cryptonator' => Cryptonator::class,
            'currency_converter' => CurrencyConverter::class,
            'currency_data_feed' => CurrencyDataFeed::class,
            'currency_layer' => CurrencyLayer::class,
            'european_central_bank' => EuropeanCentralBank::class,
            'exchange_rates_api' => ExchangeRatesApi::class,
            'fixer' => Fixer::class,
            'fixer_apilayer' => FixerApiLayer::class,
            'forge' => Forge::class,
            'national_bank_of_georgia' => NationalBankOfGeorgia::class,
            'national_bank_of_republic_belarus' => NationalBankOfRepublicBelarus::class,
            'national_bank_of_romania' => NationalBankOfRomania::class,
            'national_bank_of_ukraine' => NationalBankOfUkraine::class,
            'open_exchange_rates' => OpenExchangeRates::class,
            'array' => PhpArray::class,
            'russian_central_bank' => RussianCentralBank::class,
            'webservicex' => WebserviceX::class,
            'xignite' => Xignite::class,
            'coin_layer' => CoinLayer::class,
            'xchangeapi' => XchangeApi::class,
            'fastforex' => FastForex::class,
            'abstract_api' => AbstractApi::class,
            'exchangeratehost' => ExchangerateHost::class,
            'apilayer_fixer' => ApiLayer\Fixer::class,
            'apilayer_currency_data' => ApiLayer\CurrencyData::class,
            'apilayer_exchange_rates_data' => ApiLayer\ExchangeRatesData::class
        ];
    }
}
