import { ButtonBlock, ButtonGroupBlock, DividerBlock, HeadingBlock, IconBlock, ImageBlock, ListBlock, RichTextBlock, SpacerBlock, VideoBlock } from './components/basic.jsx';
import { ColumnBlock, ColumnsBlock, ContainerBlock, SectionBlock } from './components/layout.jsx';
import { AccordionBlock, CardsBlock, CtaBlock, FaqBlock, HeroBlock, QuoteBlock, StatisticsBlock } from './components/content.jsx';
import { NewsBlock } from './components/collections.jsx';
import { CustomBlock, GlobalRefBlock } from './components/reusable.jsx';

/**
 * Block type slug → React component. Must mirror config('pacms.blocks.types').
 * accordion-item is rendered by its parent Accordion.
 */
export const components = {
    section: SectionBlock,
    container: ContainerBlock,
    columns: ColumnsBlock,
    column: ColumnBlock,
    heading: HeadingBlock,
    'rich-text': RichTextBlock,
    list: ListBlock,
    image: ImageBlock,
    button: ButtonBlock,
    'button-group': ButtonGroupBlock,
    icon: IconBlock,
    video: VideoBlock,
    divider: DividerBlock,
    spacer: SpacerBlock,
    hero: HeroBlock,
    cards: CardsBlock,
    statistics: StatisticsBlock,
    accordion: AccordionBlock,
    faq: FaqBlock,
    quote: QuoteBlock,
    cta: CtaBlock,
    news: NewsBlock,
    'global-ref': GlobalRefBlock,
    // Every custom block type ("custom/…") shares one component.
    'custom/*': CustomBlock,
};

export const CUSTOM_COMPONENT = 'custom/*';
