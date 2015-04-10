unit Utils;

interface

uses
  Windows,
  SysUtils,
  Classes,
  Graphics,
  Controls,
  Forms,
  Dialogs,
  ExtCtrls,
  StrUtils;

function ExtractSerialzedString(const S: string; var Ret: string): Boolean;

implementation

// Fmsg_time_dt := DateUtils.UnixToDateTime(Round(msg_time));
// Fmsg_time_str := SysUtils.FormatDateTime('yyyy-mm-dd hh:nn:ss', msg_time_dt);

function ExtractSerialzedString(const S: string; var Ret: string): Boolean;
var
  i: Integer;
  L: Integer;
begin
  Result := False;
  Ret := '';
  i := Pos(':', S);
  if i <= 0 then
    Exit;
  try
    L := SysUtils.StrToInt(Copy(S, 1, i - 1));
  except
    Exit;
  end;
  if Length(S) < (i + 2) then
    Exit;
  Ret := Copy(S, i + 2, L);
  Result := True;
end;

end.