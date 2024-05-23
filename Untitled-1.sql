-- Active: 1716460558975@@localhost@3306
CREATE DATABASE K_route;
USE K_route;

Create Table trainroute (
    Station VARCHAR(50),
    Seated BOOLEAN
);

Insert Into trainroute ( Station, Seated ) VALUES ( 'Seoul', FALSE);
Insert Into trainroute ( Station, Seated ) VALUES ( 'Yeongdeungpo', FALSE);
Insert Into trainroute ( Station, Seated ) VALUES ( 'Suwon', FALSE);
Insert Into trainroute ( Station, Seated ) VALUES ( 'Pyeongtaek', FALSE);
Insert Into trainroute ( Station, Seated ) VALUES ( 'Cheonan', FALSE);
Insert Into trainroute ( Station, Seated ) VALUES ( 'Jochiwon', FALSE);

SELECT * From trainroute;