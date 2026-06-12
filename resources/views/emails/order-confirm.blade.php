<!DOCTYPE html>
<html>
<body style="font-family:sans-serif;padding:40px;color:#111;max-width:600px;">
<h2>Заказ #{{ $order->id }} принят</h2>
<p>Статус: <strong>{{ $order->status }}</strong></p>
<p>Сумма: <strong>{{ $order->total }} ₽</strong></p>
<h3>Состав заказа:</h3>
@foreach($order->items as $item)
    <p>{{ $item->product->name }} × {{ $item->quantity }} — {{ $item->price }} ₽</p>
@endforeach
<p style="color:#888;font-size:13px;margin-top:32px;">Спасибо за заказ!</p>
</body>
</html>
