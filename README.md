# Модуль «Экспорт РИНЦ XML»

Модуль (плагин) для OJS, позволяющий экспортировать метаданные статей в формате РИНЦ XML.

## Требования

  - OJS 3.4
  - PHP 8.x

## Установка

Скачайте архив [.tar.gz](https://github.com/cursedbleidd/RSCIExportOJSPlugin/releases/) и установите через менеджер модулей (plugins) в OJS.

## Использование

Зайдите в "Инструменты"->"Импорт/Экспорт", найдите модуль «Экспорт РИНЦ XML». Настройте модуль.
На вкладке "Выпуски" выберите выпуск для экспорта. 
Начентся загрузка архива с содержанием выпуска (гранки и обложка) и XML-файлом с метаданными статей. В системе articulus в проекте выпуска журнала зайдите во влкадку "Восстановить" и загрузите скачанный архив с метаданными.

При настройке укажите начало и конец промежутка копирования для статей.
Например, начало - "Введение", конец - "Литература", между этими словами скопируются текста статей.
По умолчанию текста копируются полностью или от начала промежутка до конца файла при указанном начале.
Текст извлекается из PDF гранок

### Ограничения
Этот модуль формирует все метаданные для РИНЦ, которые определены в базовой установке OJS 3.

Не формируются:
  - сквозной номер выпуска
  - рецензии статей
  - рубрики статей
  - РИНЦ ID и коды авторов, кроме ORCID

