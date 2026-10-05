-- Run after 005. Coins (points): 1 coin per $1 paid, exchange for prizes. Works alongside the discount levels.
create unique index if not exists user_points_source_uq on user_points (source_type, source_id);
alter table users alter column points_balance set default 0;
update users set points_balance = 0 where points_balance is null;

create or replace function award_points(p_user integer, p_amount numeric, p_type text, p_id integer, p_desc text) returns void language plpgsql as $$
declare pts integer := floor(coalesce(p_amount, 0));
begin
  if p_user is null or p_id < 1 or pts < 1 then return; end if;
  insert into user_points (user_id, points, amount_usd, source_type, source_id, description)
  values (p_user, pts, p_amount, p_type, p_id, p_desc) on conflict (source_type, source_id) do nothing;
  if found then update users set points_balance = coalesce(points_balance, 0) + pts where id = p_user; end if;
end $$;

-- Guide / transport bookings: notify, count spend, award coins when the deposit / balance is confirmed
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
    if new.booking_status = 'completed' and old.booking_status is distinct from 'completed' then
      update users set total_spent = total_spent + new.total_usd where id = new.user_id;
    end if;
  end if;
  return new;
end $$;

-- Hotel / restaurant bookings: notify + award coins when the payment is confirmed
create or replace function on_reservation_events() returns trigger language plpgsql as $$
begin
  if new.user_id is not null then
    if new.status is distinct from old.status then
      insert into notifications (user_id, type, title_en) values (new.user_id, 'access', 'Booking R' || new.id || ' (' || new.item_name || '): ' || new.status);
    end if;
    if new.payment_status is distinct from old.payment_status and new.payment_status = 'paid' then
      insert into notifications (user_id, type, title_en) values (new.user_id, 'access', 'Payment received for booking R' || new.id);
      perform award_points(new.user_id, coalesce(new.payment_amount_usd, new.estimated_price_usd), 'reservation_paid', new.id,
                           initcap(new.item_type) || ' reservation: ' || coalesce(new.item_name, ''));
    end if;
  end if;
  return new;
end $$;

-- Reward requests: notify the user on every status change; cancelled => coins are refunded
create or replace function on_redemption_change() returns trigger language plpgsql as $$
begin
  if new.status is distinct from old.status then
    insert into notifications (user_id, type, title_en, body_en) values (new.user_id, 'access', 'Reward "' || new.reward_name || '": ' || new.status, new.admin_notes);
    if new.status = 'cancelled' and old.status is distinct from 'cancelled' then
      perform award_points(new.user_id, new.cost_points, 'reward_refund', new.id, 'Refunded reward: ' || new.reward_name);
    end if;
  end if;
  return new;
end $$;
drop trigger if exists redemption_change on point_redemptions;
create trigger redemption_change after update on point_redemptions for each row execute function on_redemption_change();
