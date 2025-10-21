# 🚀 API Testing Guide

Complete guide to test your Rempah API easily!

---

## 📋 What's Included

I've created **3 ways** to test your APIs:

1. **Swagger UI** - Interactive web-based API documentation
2. **Postman Collection** - Import and test in Postman
3. **OpenAPI Specification** - Standard API documentation format

---

## 🎯 Option 1: Swagger UI (Easiest!)

### Access Swagger Documentation

1. **Start your Laravel server:**
   ```bash
   cd backend
   php artisan serve
   ```

2. **Open in browser:**
   ```
   http://localhost:8000/api-docs.html
   ```

3. **You'll see a beautiful interactive API documentation!** 🎉

### How to Use Swagger UI

1. **Click "Authorize" button** at the top right
2. **Login first** using the `/auth/login` endpoint:
   - Click on the endpoint
   - Click "Try it out"
   - Enter your credentials
   - Click "Execute"
   - Copy the token from the response

3. **Set the token:**
   - Click "Authorize" button again
   - Paste your token in the "Value" field (format: `Bearer YOUR_TOKEN_HERE`)
   - Click "Authorize"
   - Click "Close"

4. **Now you can test any endpoint!**
   - All requests will automatically include your token
   - Just click "Try it out" and "Execute"

---

## 📮 Option 2: Postman Collection

### Import into Postman

1. **Download the collection:**
   ```
   http://localhost:8000/postman-collection.json
   ```
   Or use the file at: `backend/public/postman-collection.json`

2. **Import in Postman:**
   - Open Postman
   - Click "Import" button
   - Select the `postman-collection.json` file
   - Click "Import"

3. **Set the base URL:**
   - The collection uses `{{base_url}}` variable
   - Default: `http://localhost:8000/api`
   - You can change it in Collection Variables

### How to Use Postman Collection

1. **Login first:**
   - Go to "🔐 Authentication" → "Login"
   - Update email/password if needed
   - Click "Send"
   - **Token is automatically saved!** ✨

2. **Test any endpoint:**
   - All endpoints automatically use the saved token
   - Just select an endpoint and click "Send"

### Collection Features

✅ **Auto-saves token** after login  
✅ **Pre-configured examples** for all endpoints  
✅ **Organized by categories** (Customers, Orders, etc.)  
✅ **Includes all new fields** (company_name2, item_group)

---

## 📄 Option 3: OpenAPI Specification

Download the OpenAPI spec:
```
http://localhost:8000/api-documentation.json
```

**Use with:**
- Swagger Editor: https://editor.swagger.io/
- Postman (import as OpenAPI)
- Insomnia
- Any OpenAPI-compatible tool

---

## 🔑 Authentication

All endpoints (except `/auth/login` and `/products`) require authentication.

### Get Token

**Request:**
```bash
POST http://localhost:8000/api/auth/login
Content-Type: application/json

{
    "email": "your@email.com",
    "password": "yourpassword"
}
```

**Response:**
```json
{
    "status": 200,
    "message": "Login successful",
    "data": {
        "token": "1|abcdef123456...",
        "user": { ... }
    }
}
```

### Use Token

Add to request headers:
```
Authorization: Bearer 1|abcdef123456...
```

---

## 📚 API Endpoints Summary

### 🔐 Authentication
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Get current user

### 👥 Customers
- `GET /api/customers` - Get all customers
- `POST /api/customers` - Create customer
- `GET /api/customers/{id}` - Get customer by ID
- `PUT /api/customers/{id}` - Update customer
- `DELETE /api/customers/{id}` - Delete customer

### 📦 Orders
- `GET /api/orders` - Get all orders (with filters)
- `POST /api/orders` - Create order
- `GET /api/orders/{id}` - Get order by ID
- `PUT /api/orders/{id}` - Update order
- `DELETE /api/orders/{id}` - Delete order

### 📝 Order Items
- `POST /api/orders-items` - Add item to order
- `DELETE /api/orders-items/{id}` - Delete order item

### 📦 Products
- `GET /api/products` - Get all products (no auth)

### 📊 Dashboard
- `GET /api/dashboard` - Get dashboard summary

### 📄 Invoices
- `GET /api/invoices` - Get all invoices
- `POST /api/invoices` - Create invoice
- `GET /api/invoices/{id}` - Get invoice
- `GET /api/invoices/{refNo}/print` - Print invoice

### 💰 Receipts
- `GET /api/receipts` - Get all receipts
- `POST /api/receipts` - Create receipt

---

## 🆕 New Fields Supported

### Customer API
```json
{
    "company_name": "ABC Trading",    // Line 1
    "company_name2": "Sdn Bhd"        // Line 2 - NEW!
}
```

### Order Item API
```json
{
    "product_id": "1",
    "quantity": 10,
    "unit_price": 25.50,
    "item_group": "Cash Sales",       // NEW!
    "is_free_good": false,
    "is_trade_return": false,
    "trade_return_is_good": true
}
```

---

## 📖 Example: Create Order with Items

```json
POST /api/orders

{
    "customer_id": "1",
    "order_date": "2025-10-21",
    "remarks": "Test order",
    "discount": 10.00,
    "tax1_percentage": 6.00,
    "items": [
        {
            "product_id": "1",
            "quantity": 10,
            "unit_price": 25.50,
            "item_group": "Cash Sales"
        },
        {
            "product_id": "2",
            "quantity": 5,
            "unit_price": 15.00,
            "is_free_good": true,
            "item_group": "Invoice"
        }
    ]
}
```

---

## 📖 Example: Add Item to Existing Order

```json
POST /api/orders-items

{
    "order_id": "1",
    "product_id": "3",
    "quantity": 5,
    "unit_price": 20.00,
    "is_free_good": false,
    "is_trade_return": true,
    "trade_return_is_good": false,
    "item_group": "Cash Sales"
}
```

---

## 🐛 Troubleshooting

### Issue: "Unauthorized" Error
**Solution:** 
1. Login first via `/auth/login`
2. Copy the token from response
3. Add to Authorization header: `Bearer YOUR_TOKEN`

### Issue: Swagger UI not loading
**Solution:**
1. Make sure Laravel server is running: `php artisan serve`
2. Access via: `http://localhost:8000/api-docs.html`
3. Check browser console for errors

### Issue: CORS errors in browser
**Solution:**
1. Update `config/cors.php` to allow your domain
2. Or use Postman (no CORS issues)

### Issue: 404 on API endpoints
**Solution:**
1. Make sure you're using `/api/` prefix
2. Example: `http://localhost:8000/api/customers`
3. Not: `http://localhost:8000/customers`

---

## 🎯 Testing Checklist

Use this checklist to test all features:

### Customers
- [ ] Create customer with company_name2
- [ ] Get all customers
- [ ] Get single customer
- [ ] Update customer
- [ ] Delete customer

### Orders
- [ ] Create order with items
- [ ] Create order without items
- [ ] Get all orders
- [ ] Filter orders by customer
- [ ] Filter orders by date range
- [ ] Get single order
- [ ] Update order
- [ ] Delete order

### Order Items
- [ ] Add normal item
- [ ] Add free good item
- [ ] Add trade return (good) item
- [ ] Add trade return (bad) item
- [ ] Add item with item_group
- [ ] Delete item

### Authentication
- [ ] Login successfully
- [ ] Token is returned
- [ ] Token works for authenticated endpoints
- [ ] Logout successfully

---

## 💡 Tips

1. **Start with Swagger UI** - It's the easiest way to explore the API
2. **Use Postman for complex testing** - Better for automation and testing workflows
3. **Save your tokens** - Both tools can save tokens for you
4. **Test incrementally** - Test simple endpoints first, then complex ones
5. **Check response codes** - 200/201 = success, 422 = validation error, 401 = auth error

---

## 📞 Quick Links

- **Swagger UI:** http://localhost:8000/api-docs.html
- **OpenAPI Spec:** http://localhost:8000/api-documentation.json
- **Postman Collection:** http://localhost:8000/postman-collection.json
- **API Base URL:** http://localhost:8000/api

---

## 🎉 You're All Set!

Everything is ready for easy API testing. Choose your preferred tool and start testing!

**Need help?** Check the examples in the Postman collection or Swagger UI.

Happy Testing! 🚀

