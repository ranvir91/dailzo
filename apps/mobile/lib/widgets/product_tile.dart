import 'package:flutter/material.dart';

import '../models/product.dart';

class ProductImageCarousel extends StatefulWidget {
  const ProductImageCarousel({
    super.key,
    required this.imageUrls,
    required this.height,
    this.borderRadius = 10,
  });

  final List<String> imageUrls;
  final double height;
  final double borderRadius;

  @override
  State<ProductImageCarousel> createState() => _ProductImageCarouselState();
}

class _ProductImageCarouselState extends State<ProductImageCarousel> {
  int _page = 0;

  @override
  Widget build(BuildContext context) {
    final hasImages = widget.imageUrls.isNotEmpty;
    return ClipRRect(
      borderRadius: BorderRadius.circular(widget.borderRadius),
      child: Container(
        height: widget.height,
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        child: Stack(
          children: [
            Positioned.fill(
              child: hasImages
                  ? PageView.builder(
                      itemCount: widget.imageUrls.length,
                      onPageChanged: (index) => setState(() => _page = index),
                      itemBuilder: (_, index) {
                        return Image.network(
                          widget.imageUrls[index],
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => const Center(
                            child: Icon(Icons.image_not_supported_outlined,
                                size: 34),
                          ),
                        );
                      },
                    )
                  : const Center(child: Icon(Icons.image_outlined, size: 34)),
            ),
            if (widget.imageUrls.length > 1)
              Positioned(
                left: 0,
                right: 0,
                bottom: 6,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(
                    widget.imageUrls.length,
                    (index) => Container(
                      margin: const EdgeInsets.symmetric(horizontal: 2),
                      width: _page == index ? 8 : 5,
                      height: 5,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(20),
                        color: _page == index ? Colors.white : Colors.white70,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class ProductTile extends StatelessWidget {
  const ProductTile({
    super.key,
    required this.product,
    required this.onTap,
    required this.onAddToCart,
    this.isServiceable = true,
  });

  final Product product;
  final VoidCallback onTap;
  final VoidCallback onAddToCart;
  final bool isServiceable;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Card(
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(8, 8, 8, 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  ProductImageCarousel(
                    imageUrls: product.imageUrls,
                    height: 110,
                    borderRadius: 10,
                  ),
                  if (product.discountPercent > 0)
                    Positioned(
                      top: 6,
                      left: 6,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 4),
                        decoration: BoxDecoration(
                          color: const Color(0xFF1565C0),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          '${product.discountPercent}%\nOFF',
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w700,
                            fontSize: 9,
                            height: 1.05,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 7),
              const Row(
                children: [
                  Icon(Icons.star, size: 14, color: Color(0xFFF9A825)),
                  SizedBox(width: 2),
                  Text('4.5',
                      style:
                          TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style:
                    const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
              ),
              const Spacer(),
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '₹${product.currentPrice.toStringAsFixed(0)}',
                          style: const TextStyle(
                              fontWeight: FontWeight.w800, fontSize: 18),
                        ),
                        if (product.discountPercent > 0)
                          Text(
                            '₹${product.originalPrice.toStringAsFixed(0)}',
                            style: TextStyle(
                              fontSize: 13,
                              color: Colors.grey.shade600,
                              decoration: TextDecoration.lineThrough,
                            ),
                          ),
                      ],
                    ),
                  ),
                  SizedBox(
                    height: 34,
                    child: OutlinedButton(
                      onPressed: isServiceable ? onAddToCart : null,
                      style: OutlinedButton.styleFrom(
                        side: BorderSide(
                          color: isServiceable
                              ? Theme.of(context).colorScheme.primary
                              : Colors.grey,
                        ),
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8)),
                        padding: const EdgeInsets.symmetric(horizontal: 18),
                      ),
                      child: Text(
                        isServiceable ? 'ADD' : 'NOT AVAILABLE',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 11,
                          color: isServiceable ? null : Colors.grey,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
