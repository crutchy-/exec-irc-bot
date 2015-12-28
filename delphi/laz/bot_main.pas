unit bot_main;

{$mode objfpc}{$H+}

interface

uses
  Classes,
  SysUtils,
  FileUtil,
  Forms,
  Controls,
  Graphics,
  Dialogs,
  StdCtrls,
  lNet; // add '/usr/share/fpcsrc/2.6.4/utils/fppkg/lnet/' to include files paths under project options -> compiler options -> paths

type

  TSocketThread = class;
  TBotServer = class;
  TBotServerArray = class;

  { TBotMessage }

  TBotMessage = record
    Command: string;
    Data: string;
    Destination: string;
    Hostname: string;
    Nick: string;
    Params: string;
    Prefix: string;
    Server: string;
    TimeStamp: TDateTime;
    Trailing: string;
    User: string;
    Valid: Boolean;
  end;

  TBotReceiveEvent = procedure(const Server: TBotServer; const Msg: TBotMessage; const Data: string) of object;

  { TSocketThread }

  TSocketThread = class(Classes.TThread)
  private
    FBuffer: string;
    FServer: TBotServer;
    FSocket: lNet.TLTcp;
  private
    procedure SocketConnect(aSocket: TLSocket);
    procedure SocketDisconnect(aSocket: TLSocket);
    procedure SocketError(const msg: string; aSocket: TLSocket);
    procedure SocketReceive(aSocket: TLSocket);
  public
    constructor Create(CreateSuspended: Boolean);
  public
    procedure Update;
    procedure Send(const Msg: string);
    procedure Execute; override;
  public
    property Server: TBotServer read FServer write FServer;
  end;

  { TBotServer }

  TBotServer = class(TObject)
  private
    FRemoteHost: string;
    FRemotePort: Integer;
    FNickName: string;
    FUserName: string;
    FFullName: string;
    FHostName: string;
    FServerName: string;
    FNickServPasswordFileName: string;
    FHandler: TBotReceiveEvent;
    FThread: TSocketThread;
  public
    constructor Create(const Handler: TBotReceiveEvent);
  public
    procedure Connect(const RemoteHost, NickName, UserName, FullName, HostName, ServerName: string; const RemotePort: Integer);
    procedure Send(const Msg: string; const Obfuscate: Boolean = False);
  public
    property RemoteHost: string read FRemoteHost;
    property RemotePort: Integer read FRemotePort;
    property NickName: string read FNickName;
    property UserName: string read FUserName;
    property FullName: string read FFullName;
    property HostName: string read FHostName;
    property ServerName: string read FServerName;
    property NickServPasswordFileName: string read FNickServPasswordFileName;
  public
    property Handler: TBotReceiveEvent read FHandler write FHandler;
  end;

  { TBotServerArray }

  TBotServerArray = class(TObject)
  private
    FGlobalHandler: TBotReceiveEvent;
    FServers: Classes.TList;
  private
    function GetCount: Integer;
    function GetServer(const Index: Integer): TBotServer;
    function GetHostName(const HostName: string): TBotServer;
  public
    constructor Create(const GlobalHandler: TBotReceiveEvent);
    destructor Destroy; override;
  public
    function Add: TBotServer;
    function IndexOf(const HostName: string): Integer;
  public
    property Count: Integer read GetCount;
    property Servers[const Index: Integer]: TBotServer read GetServer;
    property HostNames[const HostName: string]: TBotServer read GetHostName; default;
  end;

  { TForm1 }

  TForm1 = class(TForm)
    MemoData: TMemo;
    procedure FormCreate(Sender: TObject);
    procedure FormDestroy(Sender: TObject);
  private
    FServers: TBotServerArray;
  private
    procedure ReceiveHandler(const Server: TBotServer; const Message: TBotMessage; const Data: string);
  end;

var
  Form1: TForm1;

implementation

{$R *.lfm}

function ParseMessage(const Data: string): TBotMessage;
var
  S: string;
  sub: string;
  i: Integer;
begin
  Result.Valid := False;
  Result.TimeStamp := Now;
  Result.Data := Data;
  S := Data;
  // :<prefix> <command> <params> :<trailing>
  // the only required part of the message is the command
  // if there is no prefix, then the source of the message is the server for the current connection (such as for PING)
  if Copy(Data, 1, 1) = ':' then
  begin
    i := Pos(' ', S);
    if i > 0 then
    begin
      Result.Prefix := Copy(S, 2, i - 2);
      S := Copy(S, i + 1, Length(S) - i);
    end;
  end;
  i := Pos(' :', S);
  if i > 0 then
  begin
    Result.Trailing := Copy(S, i + 2, Length(S) - i - 1);
    S := Copy(S, 1, i - 1);
  end;
  i := Pos(' ', S);
  if i > 0 then
  begin
    // params found
    Result.Params := Copy(S, i + 1, Length(S) - i);
    S := Copy(S, 1, i - 1);
  end;
  Result.Command := S;
  if Result.Command = '' then
    Exit;
  Result.Valid := True;
  if Result.Prefix <> '' then
  begin
    // prefix format: nick!user@hostname
    i := Pos('!', Result.Prefix);
    if i > 0 then
    begin
      Result.Nick := Copy(Result.Prefix, 1, i - 1);
      sub := Copy(Result.Prefix, i + 1, Length(Result.Prefix) - i);
      i := Pos('@', sub);
      if i > 0 then
      begin
        Result.User := Copy(sub, 1, i - 1);
        Result.Hostname := Copy(sub, i + 1, Length(sub) - i);
      end;
    end
    else
      Result.Nick := Result.Prefix;
  end;
  i := Pos(' ', Result.Params);
  if i <= 0 then
    Result.Destination := Result.Params;
end;

{ TSocketThread }

procedure TSocketThread.SocketConnect(aSocket: TLSocket);
begin
  FBuffer := '<< CONNECTED >>';
  Synchronize(@Update);
end;

procedure TSocketThread.SocketDisconnect(aSocket: TLSocket);
begin
  FBuffer := '<< SOCKET DISCONNECTED >>';
  Synchronize(@Update);
end;

procedure TSocketThread.SocketError(const msg: string; aSocket: TLSocket);
begin
  FBuffer := '<< SOCKET ERROR >>';
  Synchronize(@Update);
end;

procedure TSocketThread.SocketReceive(aSocket: TLSocket);
begin
  aSocket.GetMessage(FBuffer);
  if Copy(FBuffer, 1, 4) = 'PING' then
  begin
    Send('PONG ' + Trim(Copy(FBuffer, 5, Length(FBuffer) - 5)));
    Exit;
  end;
  Synchronize(@Update);
end;

constructor TSocketThread.Create(CreateSuspended: Boolean);
begin
  FreeOnTerminate := True;
  inherited Create(CreateSuspended);
end;

procedure TSocketThread.Update;
var
  Msg: TBotMessage;
begin
  if Assigned(FServer.Handler) = False then
    Exit;
  Msg := ParseMessage(FBuffer);
  Msg.Server := FServer.RemoteHost;
  FServer.Handler(FServer, Msg, FBuffer);
end;

procedure TSocketThread.Send(const Msg: string);
begin
  if FSocket.Connected then
    FSocket.SendMessage(Msg + #13#10);
end;

procedure TSocketThread.Execute;
var
  i: Integer;
begin
  try
    FSocket := lNet.TLTcp.Create(nil);
    FSocket.OnReceive := @SocketReceive;
    FSocket.OnConnect := @SocketConnect;
    FSocket.OnDisconnect := @SocketDisconnect;
    FSocket.OnError := @SocketError;
    try
      if FSocket.Connect('72.14.184.41', 6667) = False then
      begin
        FBuffer := '<< CONNECTION ERROR >>';
        Synchronize(@Update);
        Exit;
      end;
      i := 1000;
      repeat
        Dec(i);
        FSocket.CallAction;
        Sleep(50);
      until (i < 0) or (FSocket.Connected = True);
      if FSocket.Connected = False then
      begin
        FBuffer := '<< CONNECTION TIMEOUT >>';
        Synchronize(@Update);
      end;
      Send('NICK z');
      Send('USER z hostname servername :z.bot');
      while (Application.Terminated = False) and (Self.Terminated = False) and (FSocket.Connected = True) do
      begin
        FSocket.CallAction;
        ThreadSwitch;
      end;
    finally
      Send('NickServ LOGOUT');
      Send('QUIT :dafuq');
      FSocket.Free;
    end;
  except
    FBuffer := '<< EXCEPTION ERROR >>';
    Synchronize(@Update);
  end;
end;

{ TBotServer }

procedure TBotServer.Connect(const RemoteHost, NickName, UserName, FullName, HostName, ServerName: string; const RemotePort: Integer);
begin
  FRemoteHost := RemoteHost;
  FRemotePort := RemotePort;
  FNickName := NickName;
  FUserName := UserName;
  FFullName := FullName;
  FHostName := HostName;
  FServerName := ServerName;
  FThread.Start;
end;

constructor TBotServer.Create(const Handler: TBotReceiveEvent);
begin
  FHandler := Handler;
  FThread := TSocketThread.Create(True);
  FThread.Server := Self;
end;

procedure TBotServer.Send(const Msg: string; const Obfuscate: Boolean = False);
begin
  FThread.Send(Msg);
  if Obfuscate = False then
    if Assigned(FHandler) then
      FHandler(Self, ParseMessage(Msg), Msg);
end;

{ TBotServerArray }

function TBotServerArray.Add: TBotServer;
begin
  Result := TBotServer.Create(FGlobalHandler);
  FServers.Add(Result);
end;

constructor TBotServerArray.Create(const GlobalHandler: TBotReceiveEvent);
begin
  FGlobalHandler := GlobalHandler;
  FServers := Classes.TList.Create;
end;

destructor TBotServerArray.Destroy;
var
  i: Integer;
begin
  for i := 0 to Count - 1 do
    Servers[i].Free;
  FServers.Free;
  inherited;
end;

function TBotServerArray.GetCount: Integer;
begin
  Result := FServers.Count;
end;

function TBotServerArray.GetHostName(const HostName: string): TBotServer;
var
  i: Integer;
begin
  i := IndexOf(HostName);
  if i >= 0 then
    Result := Servers[i]
  else
    Result := nil;
end;

function TBotServerArray.GetServer(const Index: Integer): TBotServer;
begin
  if (Index >= 0) and (Index < Count) then
    Result := TBotServer(FServers[Index])
  else
    Result := nil;
end;

function TBotServerArray.IndexOf(const HostName: string): Integer;
var
  i: Integer;
begin
  for i := 0 to Count - 1 do
    if Servers[i].HostName = HostName then
    begin
      Result := i;
      Exit;
    end;
  Result := -1;
end;

{ TForm1 }

procedure TForm1.FormCreate(Sender: TObject);
begin
  FServers := TBotServerArray.Create(@ReceiveHandler);
  FServers.Add.Connect('72.14.184.41', 'z', 'z', 'z.bot', 'hostname', 'servername', 6667);
end;

procedure TForm1.FormDestroy(Sender: TObject);
begin
  FServers.Free;
end;

procedure TForm1.ReceiveHandler(const Server: TBotServer; const Message: TBotMessage; const Data: string);
begin
  MemoData.Lines.Add(Data);
end;

end.
