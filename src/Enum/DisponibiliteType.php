<?php

// src/Enum/DisponibiliteType.php

namespace App\Enum;

enum DisponibiliteType: string
{
    case INDISPONIBLE = "indisponible";
    case VINGT_QUATRE_HEURES = "24h";
    case DOUZE_HEURES_JOUR = "12h jour";
    case DOUZE_HEURES_NUIT = "12h nuit";
}
