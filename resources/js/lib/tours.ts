import type { TourStep } from '@/components/tour';

/**
 * The words of each guided tour. Keys match App\Enums\Tour; targets match
 * `data-tour` attributes on the page. A step whose element the user cannot
 * see (a menu their role does not allow) is skipped.
 */

/** The main menu, on every page with the sidebar. */
export const mainTour: TourStep[] = [
    {
        title: 'Welcome to Pharmacy IMS',
        body: 'A one-minute tour of where things are. Use the arrow keys or the buttons; Esc skips it. You can replay it any time from your name at the bottom of the menu.',
    },
    {
        target: 'branch',
        title: 'Your branch',
        body: 'Everything you see and post (stock, shifts, sales, prescriptions) belongs to this branch.',
    },
    {
        target: 'nav-overview',
        title: 'Dashboard',
        body: "Today's sales, low stock and batches nearing expiry at a glance.",
    },
    {
        target: 'nav-sales',
        title: 'Selling',
        body: 'POS: open a shift, then sell. Invoices: reprint, void or refund a sale (a supervisor PIN approves). Z-readings: the end-of-day reading for each terminal.',
    },
    {
        target: 'nav-compliance',
        title: 'Prescriptions and reports',
        body: 'The prescriptions you have recorded, and every report (sales, BIR, inventory, the prescription book and the S2 register), exported to Excel or PDF.',
    },
    {
        target: 'nav-inventory',
        title: 'Inventory',
        body: 'Stock on hand by batch and expiry, and the documents that change it: adjustments, disposals, stock counts and transfers.',
    },
    {
        target: 'nav-purchasing',
        title: 'Purchasing',
        body: 'Purchase orders, goods receipts (lot no. and expiry required) and returns to suppliers. Reorder suggestions start a new order.',
    },
    {
        target: 'nav-catalog',
        title: 'Catalog',
        body: 'Products with their units and prices, suppliers, customers (senior and PWD IDs) and prescribers.',
    },
    {
        target: 'nav-administration',
        title: 'Administration',
        body: 'Staff accounts, roles and what each may do, branches and terminals, store settings and the audit log.',
    },
    {
        target: 'user-menu',
        title: 'Your account and this tour',
        body: 'Profile, password, two-factor and light or dark mode. Choose "Take the tour" here to replay the tour of the page you are on.',
    },
    {
        title: "You're set",
        body: "You only see the menus your role allows. If you need something that isn't there, ask the store owner.",
    },
];

/** The Open shift page. */
export const shiftOpenTour: TourStep[] = [
    {
        target: 'shift-terminal',
        title: 'Pick your terminal',
        body: 'The register you will sell on. A terminal holds one open shift at a time, and so does each cashier.',
    },
    {
        target: 'shift-opening-cash',
        title: 'Count the opening cash',
        body: 'Count the change fund already in the drawer and enter it, e.g. 1000. Enter 0 if the drawer starts empty.',
    },
    {
        target: 'shift-open-button',
        title: 'Why it matters',
        body: 'When you close the shift you count the drawer again. Expected cash = opening cash + cash sales − change − cash out − refunds. The difference shows as over or short.',
    },
];

/** The POS selling screen. */
export const posTour: TourStep[] = [
    {
        target: 'pos-search',
        title: 'Find the item',
        body: 'Scan the barcode or type the brand or generic name (F2). Stock is taken from the batch that expires first.',
    },
    {
        target: 'pos-cart',
        title: 'Check the cart',
        body: 'Change the unit (tablet, box) and quantity here. An Rx medicine asks for its prescription; record a new one if the customer brings it.',
    },
    {
        target: 'pos-discount',
        title: 'Senior / PWD and held sales',
        body: 'Discount (F4) applies the senior citizen or PWD discount; enter their ID no. and name. Hold (F7) parks a sale to serve someone else; resume it with F8.',
    },
    {
        target: 'pos-totals',
        title: 'Totals',
        body: 'The server works out every amount, including the VAT breakdown printed on the invoice.',
    },
    {
        target: 'pos-payment',
        title: 'Take payment',
        body: 'Cash, card or e-wallet; card and e-wallet need a reference no. Split payment adds another method. The change is shown below.',
    },
    {
        target: 'pos-complete',
        title: 'Complete the sale',
        body: 'Press F12 or Enter in the amount. The invoice number is taken only when the sale saves, and a retry never posts twice.',
    },
    {
        target: 'pos-actions',
        title: 'Invoices and closing',
        body: 'Invoices: reprint, void or refund (a supervisor PIN approves). Close shift: count the drawer at the end of your shift. The ? button replays this tour.',
    },
];
