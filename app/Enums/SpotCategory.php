<?php

namespace App\Enums;

enum SpotCategory: string
{
    case Food = 'food';
    case Museum = 'museum';
    case Landmark = 'landmark';
    case Nature = 'nature';
    case Shopping = 'shopping';
    case Entertainment = 'entertainment';
    case Accommodation = 'accommodation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Food => 'Yemek',
            self::Museum => 'Müze',
            self::Landmark => 'Gezilecek Yer',
            self::Nature => 'Doğa',
            self::Shopping => 'Alışveriş',
            self::Entertainment => 'Eğlence',
            self::Accommodation => 'Konaklama',
            self::Other => 'Diğer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Food => 'orange',
            self::Museum => 'blue',
            self::Landmark => 'green',
            self::Nature => 'emerald',
            self::Shopping => 'pink',
            self::Entertainment => 'purple',
            self::Accommodation => 'slate',
            self::Other => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Food => 'UtensilsCrossed',
            self::Museum => 'Landmark',
            self::Landmark => 'MapPin',
            self::Nature => 'TreePine',
            self::Shopping => 'ShoppingBag',
            self::Entertainment => 'Music',
            self::Accommodation => 'BedDouble',
            self::Other => 'MoreHorizontal',
        };
    }
}
