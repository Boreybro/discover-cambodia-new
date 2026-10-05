-- Run after 007. Loyalty spend now counts when a payment is CONFIRMED (same moment as the coins),
-- and the whole price counts when the booking is completed. Cancelled / refunded bookings count 0.
alter table hotel_restaurant_reservations add column if not exists spend_counted numeric(12,2) not null default 0;
alter table guide_bookings add column if not exists spend_counted numeric(12,2) not null default 0;
alter table transport_bookings add column if not exists spend_counted numeric(12,2) not null default 0;

create or replace function sync_spend() returns trigger language plpgsql as $$
declare target numeric := 0; total numeric; dep numeric; paid boolean; done boolean; dead boolean;
begin
  if new.user_id is null then return new; end if;
  if TG_TABLE_NAME = 'hotel_restaurant_reservations' then
    total := coalesce(new.estimated_price_usd, 0); dep := coalesce(new.payment_amount_usd, 0);
    paid := new.payment_status = 'paid'; done := new.status = 'completed'; dead := new.status = 'cancelled';
  elsif TG_TABLE_NAME = 'guide_bookings' then
    total := coalesce(new.total_usd, 0); dep := coalesce(new.deposit_usd, 0);
    paid := new.payment_status in ('deposit_paid', 'fully_paid'); done := new.payment_status = 'fully_paid' or new.booking_status = 'completed';
    dead := new.booking_status = 'cancelled' or new.payment_status = 'refunded';
  else
    total := coalesce(new.total_usd, 0); dep := coalesce(new.deposit_usd, 0);
    paid := new.payment_status in ('deposit_paid', 'fully_paid'); done := new.payment_status = 'fully_paid' or new.booking_status = 'completed';
    dead := new.booking_status = 'cancelled' or new.payment_status = 'refunded';
  end if;
  if not dead then
    if paid then target := dep; end if;
    if done then target := total; end if;
  end if;
  if target is distinct from coalesce(old.spend_counted, 0) then
    update users set total_spent = greatest(0, coalesce(total_spent, 0) + target - coalesce(old.spend_counted, 0)) where id = new.user_id;
    new.spend_counted := target;
  end if;
  return new;
end $$;

drop trigger if exists spend_sync on hotel_restaurant_reservations;
create trigger spend_sync before update on hotel_restaurant_reservations for each row execute function sync_spend();
drop trigger if exists spend_sync on guide_bookings;
create trigger spend_sync before update on guide_bookings for each row execute function sync_spend();
drop trigger if exists spend_sync on transport_bookings;
create trigger spend_sync before update on transport_bookings for each row execute function sync_spend();

-- the old "completed adds the price" logic is replaced by the function above (avoid counting twice)
drop trigger if exists booking_completed on hotel_restaurant_reservations;
create or replace function on_partner_booking() returns trigger language plpgsql as $$
declare kind text := case TG_TABLE_NAME when 'guide_bookings' then 'guide' else 'transport' end;
begin
  if new.user_id is not null then
    if new.booking_status is distinct from old.booking_status then
      insert into notifications (user_id, type, title_en, body_en) values (new.user_id, 'access', 'Booking ' || new.reference || ': ' || new.booking_status, new.admin_notes);
    end if;
    if new.payment_status is distinct from old.payment_status and new.payment_status in ('deposit_paid', 'fully_paid') then
      insert into notifications (user_id, type, title_en) values (new.user_id, 'access', 'Payment received for booking ' || new.reference);
      perform award_points(new.user_id, new.deposit_usd, kind || '_deposit', new.id, initcap(kind) || ' booking deposit');
      if new.payment_status = 'fully_paid' then
        perform award_points(new.user_id, new.balance_usd, kind || '_balance', new.id, initcap(kind) || ' booking balance');
      end if;
    end if;
  end if;
  return new;
end $$;

-- recalculate everybody from the bookings that already exist (resets total_spent first)
update users set total_spent = 0;
update hotel_restaurant_reservations set status = status;
update guide_bookings set booking_status = booking_status;
update transport_bookings set booking_status = booking_status;
