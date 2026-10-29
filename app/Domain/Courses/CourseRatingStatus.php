<?php

namespace App\Domain\Courses;

/**
 * Where the rating of a course and tees stands: looked up on Simulator Golf Tour, waiting for a
 * choice of the user, typed by hand, or confirmed (design D1 of course-rating-sgt).
 */
enum CourseRatingStatus: string
{
    case Pending = 'pending';
    case Proposed = 'proposed';
    case ChooseCourse = 'choose_course';
    case ChooseTee = 'choose_tee';
    case Manual = 'manual';
    case NotFound = 'not_found';
    case Failed = 'failed';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Recherche en cours',
            self::Proposed => 'Proposition à confirmer',
            self::ChooseCourse => 'Parcours SGT à choisir',
            self::ChooseTee => 'Tee à choisir',
            self::Manual => 'Saisie manuelle',
            self::NotFound => 'Introuvable sur SGT',
            self::Failed => 'Recherche échouée',
            self::Confirmed => 'Confirmé',
        };
    }
}
