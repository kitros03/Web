create table if not exists teacher
(
teacherID int(10) primary key,
t_fname varchar(15) not null,
t_lname varchar(20) not null, 
email varchar(50) not null unique,
username varchar(20) not null unique,
pass varchar(100) not null
);

create table if not exists thesis
(
thesisID int(10) primary key auto_increment,
supervisor int(10) not null,
title varchar(50) not null,
th_description varchar(500),
pdf_description longblob,
assigned boolean default 0,
finalized boolean default 0, 
th_status enum ('TBG', 'RUNNING', 'DONE', 'CANCELLED') not null,
thesisGrade int(10),
FinishDate date,
foreign key (supervisor)
references teacher (teacherID) on delete cascade
);

create table if not exists student
(
studentID int(10) primary key,
s_fname varchar(15) not null,
s_lname varchar(20) not null,
address varchar(50),
email varchar(50) unique,
cellphone varchar(10) unique,
homephone varchar(10),
currentects int(4) not null,
currentNPclasses int(4) not null,
thesisID int(10),
username varchar(20) not null unique,
pass varchar(100) not null,
foreign key (thesisID)
references thesis (thesisID) on delete set null
);

create table if not exists secretary
(
secretaryID int(10) primary key,
secr_fname varchar(15) not null,
secr_lname varchar(20) not null,
username varchar(20) not null unique,
pass varchar(100) not null
);


create table if not exists committee
(
thesisID int (10) primary key,
supervisor int(10) not null,
member1 int(10),
member2 int(10),
m1_confirmation boolean,
m2_confirmation boolean,
foreign key (thesisID)
references thesis (thesisID) on delete cascade,
foreign key (supervisor)
references thesis (supervisor) on delete cascade,
foreign key (member1)
references teacher (teacherID),
foreign key (member2)
references teacher (teacherID)
);
create table if not exists announcements
(
announcementID int(10) primary key auto_increment,
announcementTitle varchar(50),
announcementDate date,
announcementDesc varchar(500)
);
 create table if not exists committeeInvitations
 (
 invitationID int primary key auto_increment,
 senderID int(10) not null,
 receiverID int(10) not null,
 invitationDate date not null,
 response boolean,
 responseDate date,
 foreign key (senderID)
 references student (studentID),
 foreign key (receiverID)
 references teacher (teacherID)
 );


