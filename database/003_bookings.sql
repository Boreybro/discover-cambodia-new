-- When an admin marks a hotel/restaurant booking "completed", add its amount to the user's total spent
-- (this is what moves users up the 5 loyalty levels).
create or replace function on_booking_completed() returns trigger language plpgsql as $$
begin
  if new.status = 'completed' and old.status is distinct from 'completed' and new.user_id is not null then
    update users set total_spent = total_spent + coalesce(new.payment_amount_usd, new.estimated_price_usd, 0) where id = new.user_id;
  end if;
  return new;
end $$;
drop trigger if exists booking_completed on hotel_restaurant_reservations;
create trigger booking_completed after update on hotel_restaurant_reservations for each row execute function on_booking_completed();
