<?php

namespace App\Enums;

/**
 * The guided tours in the app. Each starts on its own the first time a user
 * reaches it, and is remembered per user once finished or skipped.
 */
enum Tour: string
{
    /** The main menu, shown on any page with the sidebar. */
    case Main = 'main';

    /** The Open shift page: terminal and opening cash. */
    case ShiftOpen = 'shift-open';

    /** The POS selling screen. */
    case Pos = 'pos';
}
