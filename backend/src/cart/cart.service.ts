import { Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';

interface CartItem {
  id: string;
  productId: string;
  quantity: number;
}

interface Cart {
  id: string;
  items: CartItem[];
}

@Injectable()
export class CartService {
  private readonly carts = new Map<string, Cart>();

  private getOrCreateCart(userId: string): Cart {
    let cart = this.carts.get(userId);
    if (!cart) {
      cart = { id: `cart-${userId}`, items: [] };
      this.carts.set(userId, cart);
    }
    return cart;
  }

  getCart(userId: string) {
    return successResponse(this.getOrCreateCart(userId), 'Cart fetched successfully');
  }

  addItem(userId: string, dto: { productId: string; quantity: number }) {
    const cart = this.getOrCreateCart(userId);
    cart.items.push({ id: `cart-item-${Date.now()}`, productId: dto.productId, quantity: dto.quantity });
    return successResponse(cart, 'Cart item added successfully');
  }

  updateItem(userId: string, id: string, quantity: number) {
    const cart = this.getOrCreateCart(userId);
    cart.items = cart.items.map((item) => (item.id === id ? { ...item, quantity } : item));
    return successResponse(cart, 'Cart item updated successfully');
  }

  removeItem(userId: string, id: string) {
    const cart = this.getOrCreateCart(userId);
    cart.items = cart.items.filter((item) => item.id !== id);
    return successResponse(cart, 'Cart item removed successfully');
  }
}
