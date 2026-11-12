import { router, usePage } from '@inertiajs/react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { http } from '@/lib/http';
import { complete } from '@/routes/tours';

export type TourStep = {
    /** `data-tour` value of the element to point at; none = a centred card. */
    target?: string;
    title: string;
    body: string;
    /** Only shown to users holding one of these permissions. */
    permission?: string | string[];
};

/** Fired by "Take the tour" in the user menu and the POS header. */
export const TOUR_START_EVENT = 'tour:start';

export function startTour(): void {
    window.dispatchEvent(new Event(TOUR_START_EVENT));
}

/**
 * Page tours mounted right now. While one is mounted, "Take the tour"
 * replays that page's tour instead of the main menu tour.
 */
let mountedPageTours = 0;

const POS_SHORTCUTS = ['F2', 'F4', 'F7', 'F8', 'F12'];
const CARD_WIDTH = 320;
const GAP = 12;
const PAD = 6;

type Rect = { top: number; left: number; width: number; height: number };

function findTarget(target: string): HTMLElement | null {
    const element = document.querySelector<HTMLElement>(
        `[data-tour="${target}"]`,
    );

    if (!element) {
        return null;
    }

    const rect = element.getBoundingClientRect();

    // Hidden (e.g. the sidebar on a phone): skip the step.
    return rect.width > 0 && rect.height > 0 ? element : null;
}

function cardPosition(
    rect: Rect,
    cardHeight: number,
): { top: number; left: number } {
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const clampTop = (top: number) =>
        Math.min(Math.max(top, GAP), viewportHeight - cardHeight - GAP);
    const clampLeft = (left: number) =>
        Math.min(Math.max(left, GAP), viewportWidth - CARD_WIDTH - GAP);

    if (rect.left + rect.width + PAD + GAP + CARD_WIDTH < viewportWidth) {
        return {
            top: clampTop(rect.top),
            left: rect.left + rect.width + PAD + GAP,
        };
    }

    if (rect.left - PAD - GAP - CARD_WIDTH > 0) {
        return {
            top: clampTop(rect.top),
            left: rect.left - PAD - GAP - CARD_WIDTH,
        };
    }

    const below = rect.top + rect.height + PAD + GAP;

    return {
        top:
            below + cardHeight < viewportHeight
                ? below
                : clampTop(rect.top - PAD - GAP - cardHeight),
        left: clampLeft(rect.left),
    };
}

/**
 * A guided tour: dims the page, highlights one element at a time and
 * explains it. Starts on its own the first time a user meets it, and again
 * whenever "Take the tour" is chosen. Finishing or skipping it is saved on
 * the user, so it does not start on its own again.
 *
 * Mark elements with `data-tour="<target>"`. Steps whose element is not on
 * the page (a menu the role cannot see, the sidebar on a phone) are skipped.
 *
 * @example
 * <Tour tourKey="pos" steps={posTour} />
 */
export function Tour({
    tourKey,
    steps,
    page = true,
    waitFor,
}: {
    tourKey: string;
    steps: TourStep[];
    /** A page tour (true) or the main menu tour shown on every page (false). */
    page?: boolean;
    /** Another tour that must be finished before this one starts on its own. */
    waitFor?: string;
}) {
    const { completedTours } = usePage().props.auth;
    const can = useCan();
    const [running, setRunning] = useState(false);
    const [visible, setVisible] = useState<TourStep[]>([]);
    const [index, setIndex] = useState(0);
    const [rect, setRect] = useState<Rect | null>(null);
    const [cardHeight, setCardHeight] = useState(180);
    const card = useRef<HTMLDivElement>(null);
    const nextButton = useRef<HTMLButtonElement>(null);

    const done = completedTours.includes(tourKey);
    const ready = !waitFor || completedTours.includes(waitFor);

    const start = useCallback(() => {
        const available = steps.filter(
            (step) =>
                (!step.permission || can(step.permission)) &&
                (!step.target || findTarget(step.target) !== null),
        );

        if (available.length === 0) {
            return;
        }

        setVisible(available);
        setIndex(0);
        setRunning(true);
    }, [steps, can]);

    const finish = useCallback(() => {
        setRunning(false);

        if (!done) {
            void http
                .post(complete(tourKey).url, {})
                .then(() => router.reload({ only: ['auth'] }));
        }
    }, [done, tourKey]);

    // First visit: start on its own once the page has drawn.
    useEffect(() => {
        if (done || !ready) {
            return;
        }

        const timer = window.setTimeout(start, 400);

        return () => window.clearTimeout(timer);
    }, [done, ready, start]);

    // "Take the tour": a page tour wins over the main menu tour.
    useEffect(() => {
        if (page) {
            mountedPageTours += 1;
        }

        const onStart = () => {
            if (page || mountedPageTours === 0) {
                start();
            }
        };

        window.addEventListener(TOUR_START_EVENT, onStart);

        return () => {
            window.removeEventListener(TOUR_START_EVENT, onStart);

            if (page) {
                mountedPageTours -= 1;
            }
        };
    }, [page, start]);

    const step = running ? visible[index] : undefined;

    // Follow the highlighted element as the page scrolls or resizes.
    useLayoutEffect(() => {
        if (!step) {
            return;
        }

        const element = step.target ? findTarget(step.target) : null;
        element?.scrollIntoView({ block: 'nearest', inline: 'nearest' });

        const measure = () => {
            const box = element?.getBoundingClientRect();
            setRect(
                box
                    ? {
                          top: box.top,
                          left: box.left,
                          width: box.width,
                          height: box.height,
                      }
                    : null,
            );
            setCardHeight(card.current?.offsetHeight ?? 180);
        };

        measure();
        window.addEventListener('resize', measure);
        window.addEventListener('scroll', measure, true);
        nextButton.current?.focus();

        return () => {
            window.removeEventListener('resize', measure);
            window.removeEventListener('scroll', measure, true);
        };
    }, [step]);

    const last = index === visible.length - 1;
    const next = useCallback(
        () => (last ? finish() : setIndex((current) => current + 1)),
        [last, finish],
    );
    const back = () => setIndex((current) => Math.max(0, current - 1));

    useEffect(() => {
        if (!running) {
            return;
        }

        // Capture so the POS shortcuts do not fire while the tour is open.
        const onKey = (event: KeyboardEvent) => {
            const keys: Record<string, () => void> = {
                Escape: finish,
                ArrowRight: next,
                ArrowLeft: back,
            };
            const action = keys[event.key];

            if (action || POS_SHORTCUTS.includes(event.key)) {
                event.preventDefault();
                event.stopPropagation();
                action?.();
            }
        };

        window.addEventListener('keydown', onKey, true);

        return () => window.removeEventListener('keydown', onKey, true);
    }, [running, finish, next]);

    if (!step) {
        return null;
    }

    const position = rect
        ? cardPosition(rect, cardHeight)
        : {
              top: (window.innerHeight - cardHeight) / 2,
              left: (window.innerWidth - CARD_WIDTH) / 2,
          };

    return (
        <div className="fixed inset-0 z-[100]">
            {/* Blocks clicks on the page while the tour is open. */}
            <div
                className={
                    rect ? 'absolute inset-0' : 'absolute inset-0 bg-black/55'
                }
                onClick={(event) => event.stopPropagation()}
            />
            {rect && (
                <div
                    aria-hidden
                    className="pointer-events-none absolute rounded-lg ring-2 ring-primary transition-all duration-200"
                    style={{
                        top: rect.top - PAD,
                        left: rect.left - PAD,
                        width: rect.width + PAD * 2,
                        height: rect.height + PAD * 2,
                        boxShadow: '0 0 0 9999px rgb(0 0 0 / 0.55)',
                    }}
                />
            )}
            <div
                ref={card}
                role="dialog"
                aria-modal="true"
                aria-labelledby="tour-title"
                aria-describedby="tour-body"
                className="absolute rounded-lg border bg-popover p-4 text-popover-foreground shadow-lg transition-all duration-200"
                style={{
                    top: position.top,
                    left: position.left,
                    width: CARD_WIDTH,
                }}
            >
                <p className="text-xs text-muted-foreground">
                    {index + 1} of {visible.length}
                </p>
                <h2 id="tour-title" className="mt-1 font-semibold">
                    {step.title}
                </h2>
                <p
                    id="tour-body"
                    className="mt-1.5 text-sm text-muted-foreground"
                >
                    {step.body}
                </p>
                <div className="mt-4 flex items-center gap-2">
                    {!last && (
                        <Button variant="ghost" size="sm" onClick={finish}>
                            Skip tour
                        </Button>
                    )}
                    <div className="ml-auto flex gap-2">
                        {index > 0 && (
                            <Button variant="outline" size="sm" onClick={back}>
                                Back
                            </Button>
                        )}
                        <Button ref={nextButton} size="sm" onClick={next}>
                            {last ? 'Done' : 'Next'}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
