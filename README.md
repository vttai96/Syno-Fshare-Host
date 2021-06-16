# FsharedotVN

Fshare.vn Host Module for Synolgoy Download Station.

## Chức năng

- Lấy link tải file
- Lấy link tải thư mục
- Hỗ trợ getlink thư mục con
- Hỗ trợ mật khẩu 

## Cài đặt

1. Tải file FshareVN.host
2. Đăng nhập vào NAS Synology (https://ip_nas:5001)
3. Mở Download Station package
4. Mở cài đặt chọn File Hosting
5. Nhấn Add để add file FshareVN.host
6. Bỏ chọn (hoặc Delete) các Host Module cho Fshare khác
7. Nhấn Edit để nhập tài khoản VIP Fshare

## Sử dụng

1. Tải file
- Tạo task mới, nhập link
- Nhấn OK để tải

2. Tải thư mục
- Tạo task mới, nhập link
- Nhấn OK để tải về file txt chứa danh sách link  file
- Tạo task mới, chọn file vừa tải về
- Nhấn OK để tải toàn bộ các file trong thư mục

## Định dạng link

1. File
    ```
    https://www.fshare.vn/file/xxxxxxxxxxxx , [pwd]
    ```
    [x] `https://www.fshare.vn/file/xxxxxxxxxxxx` \
    [x] `https://www.fshare.vn/file/xxxxxxxxxxxx , pwd` \
    [x] `fshare.vn/file/xxxxxxxxxxxx` \
    [x] `fshare.vn/file/xxxxxxxxxxxx , pwd` 
    
2. Folder
    ```
    https://www.fshare.vn/folder/xxxxxxxxxxxx , [pwd], [opt]
    opt=1 để getlink gồm thư mục con
    ```
    [x] `https://www.fshare.vn/folder/xxxxxxxxxxxx` \
    [x] `https://www.fshare.vn/folder/xxxxxxxxxxxx , pwd` \
    [x] `https://www.fshare.vn/folder/xxxxxxxxxxxx , pwd , 1` \
    [x] `https://www.fshare.vn/folder/xxxxxxxxxxxx , , 1` \
    [x] `fshare.vn/folder/xxxxxxxxxxxx` \
    [x] `fshare.vn/folder/xxxxxxxxxxxx , pwd` \
    [x] `fshare.vn/folder/xxxxxxxxxxxx , pwd , 1` \
    [x] `fshare.vn/folder/xxxxxxxxxxxx , , 1`
    
## Tham khảo
* https://github.com/giangvo/synology-fshare
* https://www.fshare.vn/api-doc
* http://global.download.synology.com/download/Document/DeveloperGuide/Developer_Guide_to_File_Hosting_Module.pdf
