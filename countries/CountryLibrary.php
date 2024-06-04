<?php

namespace Countries;

class CountryLibrary {
    protected $countries;

    public function __construct() {
        // Загрузка данных о странах
        $this->countries = include 'countries.php';
    }

    /**
     * Получить официальное название страны по двухбуквенному коду.
     *
     * @param string $alpha2Code Двухбуквенный код страны ISO 3166.
     * @param string $language Язык названия ('en' для английского, 'ru' для русского).
     * @return string|null Официальное название страны или null, если страна не найдена.
     */
    public function getOfficialCountryName($alpha2Code, $language = 'en') {
        if (isset($this->countries[$alpha2Code])) {
            return $this->countries[$alpha2Code][$language] ?? null;
        }
        return null;
    }
}
