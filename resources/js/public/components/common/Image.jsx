/**
 * Responsive image from the API's image object (CMS-ARCHITECTURE.md §24.5):
 * srcset/sizes, explicit dimensions (no layout shift), lazy loading below the fold,
 * a blurred placeholder while loading and the focal point for cropping.
 *
 * @param {{
 *   image: import('../../utils/initialData.js').ImageData,
 *   priority?: boolean,
 *   className?: string,
 *   sizes?: string,
 * }} props
 */
export default function Image({ image, priority = false, className = '', sizes }) {
    const focal = image.focal_point ? `${image.focal_point.x * 100}% ${image.focal_point.y * 100}%` : undefined;

    return (
        <img
            src={image.src}
            srcSet={image.srcset ?? undefined}
            sizes={image.srcset ? (sizes ?? image.sizes ?? '100vw') : undefined}
            width={image.width ?? undefined}
            height={image.height ?? undefined}
            alt={image.alt}
            loading={priority ? 'eager' : 'lazy'}
            fetchPriority={priority ? 'high' : undefined}
            decoding="async"
            className={className}
            style={{
                objectPosition: focal,
                backgroundImage: image.placeholder ? `url(${image.placeholder})` : undefined,
                backgroundSize: 'cover',
            }}
        />
    );
}
