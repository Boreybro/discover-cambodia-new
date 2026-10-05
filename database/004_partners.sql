-- Run after 003. Guide + transport booking automation.
insert into app_settings values
 ('deposit_pct','50'),
 ('payment_info','Pay the deposit by bank transfer / ABA Pay to: [your account name & number]. Write your booking reference in the transfer note, then upload the receipt.')
 on conflict do nothing;

-- Approved guide application => appears in the public guide list
create or replace function on_guide_approved() returns trigger language plpgsql as $$
begin
  if new.status = 'approved' and old.status is distinct from 'approved'
     and not exists (select 1 from guides g where g.id_card_no = new.id_card_no and g.phone = new.phone) then
    insert into guides (name,phone,email,telegram,bio,languages,provinces,daily_rate_usd,years_exp,profile_photo,id_card_no,id_card_photo,
      guarantor_name,guarantor_phone,guarantor_id,guarantor_rel,warranty_letter,dob,gender,home_address,status,is_active)
    values (new.name,new.phone,new.email,new.telegram,new.bio,new.languages,new.provinces,new.daily_rate_usd,new.years_exp,new.profile_photo,new.id_card_no,new.id_card_photo,
      new.guarantor_name,new.guarantor_phone,new.guarantor_id,new.guarantor_rel,new.warranty_letter,new.dob,new.gender,new.home_address,'approved',1);
  end if;
  return new;
end $$;
drop trigger if exists guide_approved on guide_applications;
create trigger guide_approved after update on guide_applications for each row execute function on_guide_approved();

-- Approved transport application => appears in the public transport list
create or replace function on_partner_approved() returns trigger language plpgsql as $$
begin
  if new.status = 'approved' and old.status is distinct from 'approved'
     and not exists (select 1 from transport_partners p where p.id_card_no = new.id_card_no and p.phone = new.phone) then
    insert into transport_partners (name,phone,email,telegram,home_address,id_card_no,id_card_photo,vehicle_type,vehicle_model,vehicle_capacity,license_plate,
      driving_license,vehicle_photo,base_province,price_per_day,warranty_letter,guarantor_name,guarantor_phone,guarantor_id,guarantor_rel,status,is_active)
    values (new.name,new.phone,new.email,new.telegram,new.home_address,new.id_card_no,new.id_card_photo,new.vehicle_type,new.vehicle_model,new.vehicle_capacity,new.license_plate,
      new.driving_license,new.vehicle_photo,new.base_province,new.price_per_day,new.warranty_letter,new.guarantor_name,new.guarantor_phone,new.guarantor_id,new.guarantor_rel,'approved',1);
  end if;
  return new;
end $$;
drop trigger if exists partner_approved on transport_applications;
create trigger partner_approved after update on transport_applications for each row execute function on_partner_approved();

-- Guide/transport booking changes => notify the user; completed => counts toward loyalty spend
create or replace function on_partner_booking() returns trigger language plpgsql as $$
begin
  if new.user_id is not null then
    if new.booking_status is distinct from old.booking_status then
      insert into notifications (user_id, type, title_en, body_en) values (new.user_id, 'access', 'Booking ' || new.reference || ': ' || new.booking_status, new.admin_notes);
    end if;
    if new.payment_status is distinct from old.payment_status and new.payment_status in ('deposit_paid','fully_paid') then
      insert into notifications (user_id, type, title_en) values (new.user_id, 'access', 'Payment received for booking ' || new.reference);
    end if;
    if new.booking_status = 'completed' and old.booking_status is distinct from 'completed' then
      update users set total_spent = total_spent + new.total_usd where id = new.user_id;
    end if;
  end if;
  return new;
end $$;
drop trigger if exists guide_booking_events on guide_bookings;
create trigger guide_booking_events after update on guide_bookings for each row execute function on_partner_booking();
drop trigger if exists transport_booking_events on transport_bookings;
create trigger transport_booking_events after update on transport_bookings for each row execute function on_partner_booking();
